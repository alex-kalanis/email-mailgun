# Email Api

Sending emails - via service Mailgun 

Contains libraries for sending emails via Mailgun 

# PHP Installation

```
{
    "require": {
        "alex-kalanis/email-mailgun": "dev-master"
    }
}
```

(Refer to [Composer Documentation](https://github.com/composer/composer/blob/master/doc/00-intro.md#introduction) if you are not
familiar with composer)


# PHP Usage

1.) Use your autoloader (if not already done via Composer autoloader)

2.) Add service into the "EmailApi\Sending" constructor. Beware necessary params for your use case.

3.) Just call sending as described in the "EmailApi\Sending".
