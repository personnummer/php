<?php

namespace Personnummer\Tests;

class PersonnummerData
{
    public function __construct(array $p)
    {
        $this->longFormat = $p['long_format'];
        $this->shortFormat = $p['short_format'];
        $this->separatedFormat = $p['separated_format'];
        $this->separatedLong = $p['separated_long'];
        $this->valid = $p['valid'];
        $this->type = $p['type'];
        $this->isMale = $p['isMale'];
        $this->isFemale = $p['isFemale'];
    }
    public string $longFormat;
    public string $shortFormat;
    public string $separatedFormat;
    public string $separatedLong;
    public bool $valid;
    public string $type;
    public bool $isMale;
    public bool $isFemale;
}
