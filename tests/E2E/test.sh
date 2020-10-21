#!/usr/bin/env bash

# waits for zipkin server to be available
ZIPKIN_SERVER_HOSTPORT=localhost:9411
APP_HOSTPORT=localhost:8002

NUMBER_OF_RETRIES=5
RETRY_COUNT=0
while : ; do
  if [[ "$RETRY_COUNT" == "$NUMBER_OF_RETRIES" ]]; then
    echo "Failed to connect to $ZIPKIN_SERVER_HOSTPORT after $NUMBER_OF_RETRIES retries."
    exit 1
  fi

  if [[ "$RETRY_COUNT" != "0" ]]; then
    echo "Retry number $RETRY_COUNT."
  fi
  (curl -s -o /dev/null "http://$ZIPKIN_SERVER_HOSTPORT/health") && break
  RETRY_COUNT=$((RETRY_COUNT+1)) 
  sleep 1
done

# calls the test app
NUMBER_OF_RETRIES=5
RETRY_COUNT=0
while : ; do
  if [[ "$RETRY_COUNT" == "$NUMBER_OF_RETRIES" ]]; then
    echo "Failed to connect to $APP_HOSTPORT after $NUMBER_OF_RETRIES retries."
    exit 1
  fi

  if [[ "$RETRY_COUNT" != "0" ]]; then
    echo "Retry number $RETRY_COUNT."
  fi
  (curl -s -o /dev/null "http://$APP_HOSTPORT/_health") && break
  RETRY_COUNT=$((RETRY_COUNT+1)) 
  sleep 3
done

# wait just in case
sleep 1

TRACES=$(curl -s $ZIPKIN_SERVER_HOSTPORT/api/v2/traces)

# makes sure we get one trace
test $(echo "$TRACES" | jq '.[0] | length') -eq 1

# makes sure the trace does not contain errors
test "$(echo "$TRACES" | jq -c '.[0][0].tags.error')" = "null"

# makes sure the span has the right name
test "$(echo "$TRACES" | jq -cr ".[0][0].name")" = "get /_health"

exit $?
