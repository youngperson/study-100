package response

import (
	"encoding/json"
	"net/http"
)

func Rfail(w http.ResponseWriter, errorReason string) http.ResponseWriter {
	mapInstance := make(map[string]interface{}, 0)
	mapInstance["code"] = 1
	mapInstance["msg"] = errorReason
	mapInstance["data"] = nil
	jsonStr, _ := json.Marshal(mapInstance)
	w.Write([]byte(jsonStr))
	return w
}

func Rsucc(w http.ResponseWriter, value interface{}) http.ResponseWriter {
	mapInstance := make(map[string]interface{}, 0)
	mapInstance["code"] = 0
	mapInstance["data"] = value
	jsonStr, _ := json.Marshal(mapInstance)
	w.Write([]byte(jsonStr))
	return w
}
