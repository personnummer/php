<?php

namespace Personnummer;

use Exception;

class PersonnummerException extends Exception
{
    /**
     * PersonnummerException constructor.
     *
     * @param string         $message
     * @param integer        $code
     * @param null|Exception $previous
     */
    public function __construct(
        string $message = 'Invalid swedish social security number',
        int $code = 400,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
