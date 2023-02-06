CONTENTS OF THIS FILE
---------------------
* Introduction
* Requirements
* Installation
* Configuration
* Plugin system
* Maintainers

INTRODUCTION
------------

Currency exchange rates module for Drupal Commerce 2.

Features
1. Manual or remote exchange rates sources.
2. Built for Commerce 2
3. Separate config yml files for exchange rates from the plugin definition.
4. Provide default price conversion calculator service.
5. Plugin based system.
6. Integration with Commerce Currency Resolver module

Builtin exchange rates plugins are:
1. Manual plugin
2. TransferWise
2. Fixer (free and paid)
3. Currencylayer (free and paid)
3. European Central Bank


REQUIREMENTS
------------
This module requires Drupal Commerce 2 and it's submodule price.


INSTALLATION
------------
Install the Commerce Exchanger module as you would normally install
any Drupal contrib module.
Visit https://www.drupal.org/node/1897420 for further information.


CONFIGURATION
--------------
    1. Navigate to Administration > Extend and enable the Commerce Exchanger
       module.
    2. Navigate to Home > Administration > Commerce > Configuration
                   > Exchange rates.

PLUGIN SYSTEM
--------------
See examples of implementation for external providers inside plugin namespace.
`\Drupal\commerce_exchanger\Plugin\Commerce\ExchangerProvider\`

See `\Drupal\commerce_exchanger\Annotation\CommerceExchangerProvider`
for available properties on CommerceExchangerProvider annotation plugin type.

In most cases it would be enough to make proper annotation type
and implement two functions inside your plugin:
`ExchangerProviderRemoteInterface::apiUrl`
`ExchangerProviderRemoteInterface::getRemoteData`

Note that function `getRemoteData()` should return array keyed as examples
below. If you follow that format, you don't need to implement anything more
in your plugin.

```
['HRK' => '1.3', 'USD' => '1.666']
```

or

```
['base' => 'USD', 'rates' => ['HRK' => '1.3', 'EUR' => '1.666']]
```

Either format will work.

PLUGIN EXAMPLES
--------------
https://www.drupal.org/project/commerce_exchanger_hnb
https://www.drupal.org/project/commerce_exchanger_nbu

PLUGIN ANNOTATION
--------------
All options `\Drupal\commerce_exchanger\Annotation\CommerceExchangerProvider`

Some specifics to single out:

**base_currency**

When external source locked to single currency source (as ECB).
If you use this annotation, enterprise mode is not available,
and exchange rates always calculated as cross conversion
between non default currencies.

**transform_rates**

Usually most of the exchange rate providers are giving ratio based
on base currency. Like 1 EUR = 1.116 USD, and you get data in json as
```
[
    {
        "rate": 1.166,
        "source": "EUR",
        "target": "USD",
        "time": "2018-08-31T10:43:31+0000"
    }
]
```

But some of them are doing reverse.
```
[
    {
        "rate": 0.84,
        "source": "EUR",
        "target": "USD",
        "time": "2018-08-31T10:43:31+0000"
    }
]
```
In these cases you need us `transform_mode = TRUE` annotation on your plugin.

MAINTAINERS
-----------

The 8.x-1.x branch was created by:

 * Valentino Medimorec (valic) - https://www.drupal.org/u/valic
