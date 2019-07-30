echo 'kill command'
echo "ps -ef | grep  'go run main*' | grep -v 'grep' | cut -c 9-15"
echo "ps -ef | grep  'go run main*' | grep -v 'grep' | cut -c 9-15 | xargs kill -9"
