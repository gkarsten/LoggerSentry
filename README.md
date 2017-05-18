# Magento To Sentry Logger

The purpose of this project is to log the magento error and exception messages to sentry, too. This extension is an extension of the [Firegento Logger module](https://github.com/firegento/firegento-logger), so you need the Logger module to use the Sentry logger.

# Installation
1. Add `"maksold/logger-sentry": "dev-master"` to your `composer.json` in the `require` section.
2. Add to your `composer.json` in the `repositories` section this:
```json
{
  "type": "vcs",
  "url": "git://github.com/maksold/LoggerSentry.git"
}
```

3. Run `composer update` to update libraries.
4. Configure the module (see below).

## Configuration

After you install the module you can configure it in the backend at: `System > Configuration > Advanced > FireGento Logger > Sentry Logger`

### Exclude Exceptions from Logging:
* exclude Exceptions like below: to **default.xml**
* make sure to refresh the config 'php shell/config.php --mode <mode>'
```xml
<HackathonExcludedExceptions>
    <![CDATA[
            [
                {
                    "name":"Customweb, Authorisierung fehlgeschlagen",
                    "message":"exception 'Customweb_Payment_Exception_RecurringPaymentErrorException' with message 'Die Autorisierung ist fehlgeschlagen.",
                    "log":true
                },
                {
                    "name":"TestException",
                    "message":"TestException",
                    "log":true
                }
            ]      
    ]]>
</HackathonExcludedExceptions>
```
## Information for Developers
### Disable logger for some part of code
If you don't want to log some data to Sentry, but want to log them to another sources (e.g. simple log file), then you can disable Sentry Logger for some part of code.
```php
Mage::register('disable_sentry_logger', true); // Disable Sentry Logger

Mage:log("Some message, that will not be logged to Sentry");

Mage::unregister('disable_sentry_logger'); // Enabled Sentry Logger
```