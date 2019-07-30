package client

import (
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"

	"gopkg.in/igm/sockjs-go.v2/sockjs"
	"k8s.io/api/core/v1"
	"k8s.io/client-go/kubernetes"
	"k8s.io/client-go/kubernetes/scheme"
	"k8s.io/client-go/rest"
	"k8s.io/client-go/tools/remotecommand"
)

// PtyHandler is what remotecommand expects from a pty
type PtyHandler interface {
	io.Reader
	io.Writer
	remotecommand.TerminalSizeQueue
}

// TerminalSession implements PtyHandler (using a SockJS connection)
type TerminalSession struct {
	id            string
	bound         chan error
	sockJSSession sockjs.Session
	sizeChan      chan remotecommand.TerminalSize
}

// TerminalMessage is the messaging protocol between ShellController and TerminalSession.
//
// OP      DIRECTION  FIELD(S) USED  DESCRIPTION
// ---------------------------------------------------------------------
// bind    fe->be     SessionID      Id sent back from TerminalResponse
// stdin   fe->be     Data           Keystrokes/paste buffer
// resize  fe->be     Rows, Cols     New terminal size
// stdout  be->fe     Data           Output from the process
// toast   be->fe     Data           OOB message to be shown to the user
type TerminalMessage struct {
	Op, Data, SessionID string
	Rows, Cols          uint16
}

// TerminalSize handles pty->process resize events
// Called in a loop from remotecommand as long as the process is running
func (t TerminalSession) Next() *remotecommand.TerminalSize {
	select {
	case size := <-t.sizeChan:
		return &size
	}
}

// Read handles pty->process messages (stdin, resize)
// Called in a loop from remotecommand as long as the process is running
func (t TerminalSession) Read(p []byte) (int, error) {
	m, err := t.sockJSSession.Recv()
	if err != nil {
		return 0, err
	}

	var msg TerminalMessage
	if err := json.Unmarshal([]byte(m), &msg); err != nil {
		return 0, err
	}

	// todo 把msg的socketid写回去到改造的 K8sTerminalSession
	// todo  在write的地方在把写进去的socketid  设置出去

	switch msg.Op {
	case "stdin":
		return copy(p, msg.Data), nil
	case "resize":
		t.sizeChan <- remotecommand.TerminalSize{msg.Cols, msg.Rows}
		return 0, nil
	default:
		return 0, fmt.Errorf("unknown message type '%s'", msg.Op)
	}
}

// Write handles process->pty stdout
// Called from remotecommand whenever there is any output
func (t TerminalSession) Write(p []byte) (int, error) {
	msg, err := json.Marshal(TerminalMessage{
		Op:   "stdout",
		Data: string(p),
	})
	if err != nil {
		return 0, err
	}

	if err = t.sockJSSession.Send(string(msg)); err != nil {
		return 0, err
	}
	return len(p), nil
}

// Toast can be used to send the user any OOB messages
// hterm puts these in the center of the terminal
func (t TerminalSession) Toast(p string) error {
	msg, err := json.Marshal(TerminalMessage{
		Op:   "toast",
		Data: p,
	})
	if err != nil {
		return err
	}

	if err = t.sockJSSession.Send(string(msg)); err != nil {
		return err
	}
	return nil
}

func (t TerminalSession) Close(status uint32, reason string) {
	t.sockJSSession.Close(status, reason)
}

// terminalSessions stores a map of all TerminalSession objects
// FIXME: this structure needs locking
var terminalSessions = make(map[string]TerminalSession)

func genTerminalSessionId() (string, error) {
	bytes := make([]byte, 16)
	if _, err := rand.Read(bytes); err != nil {
		return "", err
	}
	id := make([]byte, hex.EncodedLen(len(bytes)))
	hex.Encode(id, bytes)
	return string(id), nil
}

// isValidShell checks if the shell is an allowed one
func isValidShell(validShells []string, shell string) bool {
	for _, validShell := range validShells {
		if validShell == shell {
			return true
		}
	}
	return false
}

func WaitForTerminal(k8sClient *kubernetes.Clientset, cfg *rest.Config, sessionId, shell, namespace, podName, containerName string) {
	fmt.Println(sessionId, shell, namespace, podName, containerName)
	select {
	case <-terminalSessions[sessionId].bound:
		close(terminalSessions[sessionId].bound)

		var err error
		validShells := []string{"bash", "sh", "powershell", "cmd"}
		if isValidShell(validShells, shell) {
			cmd := []string{shell}
			err = startProcess(k8sClient, cfg, namespace, podName, containerName, cmd, terminalSessions[sessionId])
		} else {
			// No shell given or it was not valid: try some shells until one succeeds or all fail
			// FIXME: if the first shell fails then the first keyboard event is lost
			for _, testShell := range validShells {
				cmd := []string{testShell}
				if err = startProcess(k8sClient, cfg, namespace, podName, containerName, cmd, terminalSessions[sessionId]); err == nil {
					break
				}
			}
		}

		if err != nil {
			terminalSessions[sessionId].Close(2, err.Error())
			return
		}

		terminalSessions[sessionId].Close(1, "Process exited")
	}
}

// handleTerminalSession is Called by net/http for any new /api/sockjs connections
func handleTerminalSession(session sockjs.Session) {
	var (
		buf             string
		err             error
		msg             TerminalMessage
		terminalSession TerminalSession
		ok              bool
	)

	if buf, err = session.Recv(); err != nil {
		log.Printf("handleTerminalSession: can't Recv: %v", err)
		return
	}

	if err = json.Unmarshal([]byte(buf), &msg); err != nil {
		log.Printf("handleTerminalSession: can't UnMarshal (%v): %s", err, buf)
		return
	}

	if msg.Op != "bind" {
		log.Printf("handleTerminalSession: expected 'bind' message, got: %s", buf)
		return
	}

	if terminalSession, ok = terminalSessions[msg.SessionID]; !ok {
		log.Printf("handleTerminalSession: can't find session '%s'", msg.SessionID)
		return
	}

	terminalSession.sockJSSession = session
	terminalSessions[msg.SessionID] = terminalSession
	terminalSession.bound <- nil
}

// CreateAttachHandler is called from main for /api/sockjs
func CreateAttachHandler(path string) http.Handler {
	return func() http.Handler {
		defer func() {
			p := recover()
			fmt.Println("CreateAttachHandler=>recover", p)
		}()
		return sockjs.NewHandler(path, sockjs.DefaultOptions, handleTerminalSession)
	}()
}

func startProcess(k8sClient kubernetes.Interface, cfg *rest.Config, namespace, podName, containerName string, cmd []string, ptyHandler PtyHandler) error {

	req := k8sClient.CoreV1().RESTClient().Post().
		Resource("pods").
		Name(podName).
		Namespace(namespace).
		SubResource("exec")

	req.VersionedParams(&v1.PodExecOptions{
		Container: containerName,
		Command:   cmd,
		Stdin:     true,
		Stdout:    true,
		Stderr:    true,
		TTY:       true,
	}, scheme.ParameterCodec)

	exec, err := remotecommand.NewSPDYExecutor(cfg, "POST", req.URL())
	if err != nil {
		return err
	}

	err = exec.Stream(remotecommand.StreamOptions{
		Stdin:             ptyHandler,
		Stdout:            ptyHandler,
		Stderr:            ptyHandler,
		TerminalSizeQueue: ptyHandler,
		Tty:               true,
	})

	if err != nil {
		return err
	}

	return nil
}
