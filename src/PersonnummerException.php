<?php

namespace Personnummer;

use Exception;

class PersonnummerException extends Exception
{
    /**
     * PersonnummerException constructor.
     *
     * @param  string  $message
     * @param  int  $code
     * @param  null|Exception  $previous
     */
    public function __construct(
        $message = 'Invalid swedish social security number',
        $code = 400,
        $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
