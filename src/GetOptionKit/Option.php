<?php
/*
 * This file is part of the {{ }} package.
 *
 * (c) Yo-An Lin <cornelius.howl@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace GetOptionKit;
use GetOptionKit\NonNumericException;
use InvalidArgumentException;

class Option 
{
    public $short;


    public $long;

    /**
     * @var string the description of this option
     */
    public $description;

    /**
     * @var string The option key
     */
    public $key;  /* key to store values */
    public $value;
    public $type;

    public $valueName; /* name for the value place holder, for printing */

    public $isa;

    public $validValues;

    public $suggestions;

    const attr_multiple = 1;
    const attr_optional = 2;
    const attr_require  = 4;
    const attr_flag     = 8;

    const type_string   = 1;
    const type_number  = 2;

    public function __construct($specString = null)
    {
        if( $specString ) {
            $this->initFromSpecString($specString);
        }
    }

    /* 
     * build spec attributes from spec string 
     *
     **/
    function initFromSpecString($specString)
    {
        $pattern = '/
        (
                (?:[a-zA-Z0-9-]+)
                (?:
                    \|
                    (?:[a-zA-Z0-9-]+)
                )?
        )
        ([:+?])?
        (?:=([si]|string|integer))?
        /x';

        if( preg_match( $pattern, $specString , $regs ) === false ) {
            throw new Exception( "Unknown spec string" );
        }

        $orig       = $regs[0];
        $name       = $regs[1];
        $attributes = @$regs[2];
        $type       = @$regs[3];

        $short = null;
        $long = null;

        // check long,short option name.
        if( strpos($name,'|') !== false ) {
            list($short,$long) = explode('|',$name);
        } elseif( strlen($name) === 1 ) {
            $short = $name;
        } elseif( strlen($name) > 1 ) {
            $long = $name;
        }

        $this->short  = $short;
        $this->long   = $long;

        // option is required.
        if( strpos($attributes,':') !== false ) {
            $this->setAttributeRequire();
        }
        // option with multiple value
        elseif( strpos($attributes,'+') !== false ) {
            $this->setAttributeMultiple();
        }
        // option is optional.(zero or one value)
        elseif( strpos($attributes,'?') !== false ) {
            $this->setAttributeOptional();
        } 

        // option is multiple value and optional (zero or more)
        elseif( strpos($attributes,'*') !== false ) {
            throw new Exception('not implemented yet');
        }
        // is a flag option
        else {
            $this->setAttributeFlag();
        }

        if( $type ) {
            if( $type === 's' || $type === 'string' ) {
                $this->setTypeString();
            }
            elseif( $type === 'i' || $type === 'integer' ) {
                $this->setTypeNumber();
            }
        }
    }


    /*
     * get the option key for result key mapping.
     */
    function getId()
    {
        if( $this->key )
            return $this->key;
        if( $this->long )
            return $this->long;
        if( $this->short )
            return $this->short;
    }

    function setAttributeRequire()
    {
        $this->attributes = self::attr_require;
    }

    function setAttributeMultiple()
    {
        $this->attributes = self::attr_multiple;
        $this->value = array();  # for value pushing
    }

    function setAttributeOptional()
    {
        $this->attributes = self::attr_optional;
    }

    function setAttributeFlag()
    {
        $this->attributes = self::attr_flag;
    }


    function isAttributeFlag()
    {
        return $this->attributes & self::attr_flag;
    }

    function isAttributeMultiple()
    {
        return $this->attributes & self::attr_multiple;
    }

    function isAttributeRequire()
    {
        return $this->attributes & self::attr_require;
    }

    function isAttributeOptional()
    {
        return $this->attributes & self::attr_optional;
    }


    public function setTypeString()
    {
        $this->type = self::type_string;
        $this->isa = 'string';
    }

    public function setTypeNumber()
    {
        $this->type = self::type_number;
        $this->isa = 'number';
    }

    public function isTypeString()
    {
        return $this->isa == 'string';
    }

    public function isTypeNumber()
    {
        return $this->isa == 'number';
    }

    public function isType($type) {
        return $this->isa === $type;
    }

    public function getTypeClass() {
        $class = 'GetOptionKit\\ValueType\\' . ucfirst($this->isa) . 'Type';
        if ( class_exists($class, true) ) {
            return new $class;
        }
        return false;
    }

    /*
     * check value constraint type
     * current for integer and string.
     */
    public function checkType($value)
    {
        if( $this->type !== null ) {
            // check type constraints
            if( $this->isTypeNumber() ) {
                if( ! is_numeric($value) )
                    throw new NonNumericException;
                $value = (int) $value;
            }
        }
        return $value;
    }

    /*
     * set option value
     */
    public function setValue($value)
    {
        if ( $type = $this->getTypeClass() ) {
            if ($type->test($value)) {
                $this->value = $type->parse($value);
            } else {
                throw new InvalidArgumentException("Invalid value for type {$this->isa}");
            }
        } else {
            $this->value = $value;
        }
    }


    /*
     * push option value, when the option accept multiple values 
     */
    function pushValue($value)
    {
        $value = $this->checkType($value);
        $this->value[] = $value;
    }

    function setDescription($desc)
    {
        $this->description = $desc;
    }

    function setValueName($name)
    {
        $this->valueName = $name;
    }


    /*
     * set option spec key for saving option result
     */
    function setKey($key)
    {
        $this->key = $key;
    }

    /*
     * get readable spec for printing
     *
     */
    function getReadableSpec()
    {
        $c1 = '';
        if( $this->short && $this->long )
            $c1 = sprintf('-%s, --%s',$this->short,$this->long);
        elseif( $this->short )
            $c1 = sprintf('-%s',$this->short);
        elseif( $this->long )
            $c1 = sprintf('--%s',$this->long );

        $valueName = 'value';
        if( $this->valueName )
            $valueName = $this->valueName;

        if( $this->isAttributeRequire() ) {
            $c1 .= " <$valueName>";
        }
        elseif( $this->isAttributeMultiple() ) {
            $c1 .= " <$valueName>+"; // better expression
        }
        elseif( $this->isAttributeOptional() ) {
            $c1 .= " [<$valueName>]";
        }
        elseif( $this->isAttributeFlag() ) {

        }
        return $c1;
    }

    function validate()
    {
        // validate current value
    }

    function __toString()
    {
        $c1 = $this->getReadableSpec();
        $return = '';
        $return .= sprintf("* key:%-8s spec:%s  desc:%s",$this->getId(), $c1,$this->description) . "\n";
        if( is_array($this->value) ) {
            $return .= '  ' . print_r(  $this->value, true ) . "\n";
        } else {
            $return .= sprintf("  value => %s" , $this->value) . "\n";
        }
        return $return;
    }


    /**
     * Value Type Setters
     *
     * @param string $type the value type, valid values are 'number', 'string', 
     *                      'file', 'boolean', you can also use your own value type name.
     *
     */
    public function isa($type) {
        $this->isa = $type;
        return $this;
    }

    /**
     * Assign validValues to member value.
     */
    public function validValues($values) {
        $this->validValues = $values;
        return $this;
    }

    /**
     * Assign suggestions
     *
     * @param Closure|Array
     */
    public function suggestions($suggestions) {
        $this->suggestions = $suggestions;
        return $this;
    }


    public function getValidValues() { 
        if (is_callable($this->validValues)) {
            return call_user_func($this->validValues);
        }
        return $this->validValues;
    }


    public function getSuggestions() { 
        if (is_callable($this->suggestions)) {
            return call_user_func($this->suggestions);
        }
        return $this->suggestions;
    }

}

