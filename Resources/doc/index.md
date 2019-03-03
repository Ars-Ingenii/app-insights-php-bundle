# Installation

Supported symfony versions: 

* `>= 3.4`
* `>= 4.0` 

## Applications that don't use Symfony Flex

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
$ composer require app-insights-php/app-insights-php-bundle
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

## Applications that use Symfony Flex

(**Not available yet**)

Open a command console, enter your project directory and execute:

```console
$ composer require app-insights-php
```

### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `app/AppKernel.php` file of your project:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            // ...
            new AppInsightsPHP\Symfony\AppInsightsPHPBundle\AppInsightsPHPBundle(),
        ];

        // ...
    }

    // ...
}
```
### Step 3: Setup Instrumentation Key

If you are using environment variables you should create new entry: 

```dotenv   
MICROSOFT_APP_INSIGHTS_INTRUMENTATION_KEY='change_me'
```

In order to obtain instrumentation key please follow [Microsoft official documentation](https://docs.microsoft.com/en-us/azure/azure-monitor/app/create-new-resource)


### Step 4: Configuration Reference

```yaml
app_insights_php:
  instrumentation_key: "%env(MICROSOFT_APP_INSIGHTS_INTRUMENTATION_KEY)%"
  exceptions:
    enabled: true
    ignored_exceptions:
      - 'Symfony\Component\HttpKernel\Exception\NotFoundHttpException'
  dependencies:
    enabled: true
  requests:
    enabled: true
  traces:
    enabled: true
  doctrine:
    track_dependency: true
  monolog:  
    handlers:
      trace: # register: app_insights_php.monolog.handler.trace - service  
        type: trace
        level: DEBUG
        bubble: true
      foo: # register: app_insights_php.monolog.handler.foo - service  
        type: trace
        level: ERROR
        bubble: true
        
monolog:
  handlers:
  app_insights:
    type: service
    id: "app_insights_php.monolog.handler.trace"
```

### Step 5: How it works

Please check our [How it works](how_it_works.md) section.