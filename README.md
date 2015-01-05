# php-is-personnummer [![Build Status](https://travis-ci.org/frozzare/php-is-personnummer.svg?branch=master)](https://travis-ci.org/frozzare/php-is-personnummer)

Validate Swedish personal identity numbers.

## Installation

```
$ composer require frozzare/is-personnummer 1.0.0
```

## Example

```php
\Personnummer::valid(6403273813);
//=> true

\Personnummer::valid('19130401+2931');
//=> true
```

See [PersonnummerTest.php](tests/PersonnummerTest.php) for more examples.

# License

MIT © [Fredrik Forsmo](https://github.com/frozzare)
