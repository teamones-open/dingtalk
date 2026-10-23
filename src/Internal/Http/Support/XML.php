<?php

namespace EasyDingTalk\Internal\Http\Support;

/** XML response conversion without deprecated global entity-loader changes. */
class XML
{
    public static function parse(string $xml): array
    {
        // SDK responses do not need a DTD. Reject it instead of enabling entities.
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            throw new \InvalidArgumentException('DTD and entity declarations are not supported.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $element = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            if ($element === false) {
                throw new \InvalidArgumentException('Invalid XML response.');
            }
            return (array) json_decode(json_encode($element, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
