package main

import (
	"log"
	"net/http"

	"gitlab.luojilab.com/k8s-dashboard/client"
)

func main() {
	http.Handle("/api/sockjs/", client.CreateAttachHandler("/api/sockjs"))
	http.HandleFunc("/container/shell", client.HandleExecShell)
	log.Println("Server started on port: 8285")
	log.Fatal(http.ListenAndServe(":8285", nil))
}
