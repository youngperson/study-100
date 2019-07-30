package client

import (
	"errors"

	"k8s.io/client-go/kubernetes"
	restclient "k8s.io/client-go/rest"
	"k8s.io/client-go/tools/clientcmd"
)

type K8sClient struct {
}

func (c *K8sClient) ClientConfig(context string) (*restclient.Config, error) {
	if context == "" {
		return nil, errors.New("必须设置上下文")
	}

	// use the current context in kubeconfig
	kubeconfigpath := "/Users/wanrenliang/.kube/config"
	config, err := clientcmd.NewNonInteractiveDeferredLoadingClientConfig(
		&clientcmd.ClientConfigLoadingRules{ExplicitPath: kubeconfigpath},
		&clientcmd.ConfigOverrides{
			CurrentContext: context,
		}).ClientConfig()
	if err != nil {
		return nil, err
	}

	return config, nil
}

func (c *K8sClient) Connect(config *restclient.Config) (*kubernetes.Clientset, error) {

	// create the clientset
	clientset, err := kubernetes.NewForConfig(config)
	if err != nil {
		return nil, err
	}

	return clientset, nil
}
