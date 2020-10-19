# E2E test

The E2E test verifies that a request is being traced in a Symfony application.

In order to run this in local:

First launch a zipkin server:

```bash
make run-zipkin
```

Then, in `terminal 1` run the test app and attach into it

```bash
make clean build run-app
```

After, in `terminal 2` run the test script

```bash
make test
```

You might be able to see the spans in http://localhost:9411/zipkin/

**Note:** You can run an specific Symfony version by executing `SYMFONY_VERSION={{SYMFONY_VERSION}} make clean build run-app`. By default we run `dev-master`.
