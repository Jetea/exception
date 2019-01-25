# exception
The Jetea Exception component.

## Installation

```
composer require jetea/exception=~2.0 -vvv
```

## Getting Started

1. vim index.php

   ```
   require __DIR__ . '/vendor/autoload.php';
   
   $handler = new \Jetea\Exception\Handler();
   (new \Jetea\Exception\HandleExceptions($handler))->handle();
   
   //undefined variable: a
   echo $a;
   ```

1. Run `php -S 127.0.0.1:9000`

1. Open a browser window and navigate to http://127.0.0.1:9000. You should be able to see the debugger page.

