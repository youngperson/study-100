package client

import (
	"fmt"
	"net/http"

	"gitlab.luojilab.com/k8s-dashboard/response"
	"k8s.io/client-go/tools/remotecommand"
)

type TerminalResponse struct {
	Id string `json:"id"`
}

func HandleExecShell(w http.ResponseWriter, r *http.Request) {
	cluster := r.FormValue("cluster")
	namespace := r.FormValue("namespace")
	pod := r.FormValue("pod")
	container := r.FormValue("container")
	if len(cluster) < 1 || len(namespace) < 1 || len(pod) < 1 || len(container) < 1 {
		response.Rfail(w, "参数错误")
		return
	}

	// 不校验了
	// var clusters = map[string]int{"bj-0": 0, "bj-1": 1}
	// if _, ok := clusters[cluster]; !ok {
	// 	response.Rfail(w, "cluster错误")
	// 	return
	// }

	// var namespaces = map[string]int{"test": 0, "prod": 1, "sim": 2, "dev": 3}
	// if _, ok := namespaces[namespace]; !ok {
	// 	response.Rfail(w, "namespace错误")
	// 	return
	// }

	sessionId, err := genTerminalSessionId()
	if err != nil {
		response.Rfail(w, fmt.Sprintf("HandleExecShell.genTerminalSessionId err:%s", err.Error()))
	}

	// config
	conn := &K8sClient{}
	clientconfig, err := conn.ClientConfig(cluster)
	if err != nil {
		response.Rfail(w, fmt.Sprintf("HandleExecShell.clientConfig error:%s", err.Error()))
		return
	}

	// k8sclient
	k8sClient, err := conn.Connect(clientconfig)
	if err != nil {
		response.Rfail(w, fmt.Sprintf("HandleExecShell.clientConn error:%s", err.Error()))
		return
	}

	terminalSessions[sessionId] = TerminalSession{
		id:       sessionId,
		bound:    make(chan error),
		sizeChan: make(chan remotecommand.TerminalSize),
	}
	go WaitForTerminal(k8sClient, clientconfig, sessionId, "", namespace, pod, container)
	response.Rsucc(w, TerminalResponse{Id: sessionId})
}
