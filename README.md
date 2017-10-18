# personnummer [![Build Status](https://travis-ci.org/personnummer/php.svg?branch=master)](https://travis-ci.org/personnummer/php)

Validate Swedish social security numbers.

## Installation

```
$ composer require frozzare/personnummer
```

## Example

```php
use Frozzare\Personnummer\Personnummer;

Personnummer::valid(6403273813);
//=> true

Personnummer::valid('19130401+2931');
//=> true
```

See [PersonnummerTest.php](tests/PersonnummerTest.php) for more examples.

## License

MIT
