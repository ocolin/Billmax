<?php

declare( strict_types = 1 );

namespace Ocolin\Billmax;

use LogicException;

class Filter
{
    /**
     * @var array<int, array{field: string, operator: string, value: string|int|float}>
     */
    private array $conditions = [];

    /**
     * @var ?string Placeholder for value field.
     */
    private ?string $pendingField;


/* WHERE FIELD
----------------------------------------------------------------------------- */

    /**
     * @param string $field Name of column.
     * @return self
     */
    public static function where( string $field ) : self
    {
        $instance = new self();
        $instance->pendingField = $field;

        return $instance;
    }



/* ALSO ADD A CONDITION
----------------------------------------------------------------------------- */

    /**
     * @param string $field Name of next condition.
     * @return self
     */
    public function also( string $field ) : self
    {
        $this->pendingField = $field;

        return $this;
    }



/* BUILD QUERY
----------------------------------------------------------------------------- */

    /**
     * @return string String build of the query.
     */
    public function build() : string
    {
        if( $this->pendingField !== null ) {
            throw new LogicException(
                message: "Filter field '{$this->pendingField}' has no operator."
            );
        }

        $output = '';

        foreach( $this->conditions as $condition )
        {
            $value = self::escape( value: (string)$condition['value'] );
            $output .= '&' . trim(
                string: $condition['field']
                . ':' . $condition['operator']
                . ':' . $value
            );
        }

        return ltrim( string: $output, characters: '&' );
    }



/* ADD A CONDITION
----------------------------------------------------------------------------- */

    /**
     * @param string $operator Operator of the condition.
     * @param string|int|float $value Value of the condition.
     * @return self
     */
    private function addCondition( string $operator, string|int|float $value ): self
    {
        if( $this->pendingField === null ) {
            throw new LogicException(
                message: "No field set. Call where() or also() before an operator."
            );
        }

        $this->conditions[] = [
            'field'    => $this->pendingField,
            'operator' => $operator,
            'value'    => (string)$value,
        ];
        $this->pendingField = null;

        return $this;
    }



/* OPERATOR FUNCTIONS
----------------------------------------------------------------------------- */

    public function eq( string|int|float $value ) : self
    {
        return $this->addCondition( operator: 'eq', value: $value );
    }

    public function ne( string|int|float $value ) : self
    {
        return $this->addCondition( operator: 'ne', value: $value );
    }

    public function like( string $value ) : self
    {
        return $this->addCondition( operator: 'like', value: $value );
    }

    public function nlike( string $value ) : self
    {
        return $this->addCondition( operator: 'nlike', value: $value );
    }

    /**
     * @param array<string, string|int|float|bool> $value Elements.
     * @return self
     */
    public function in( array $value ) : self
    {
        return $this->addCondition(
            operator: 'in',
               value: '[' . implode( separator: ',', array: $value ) . ']'
        );
    }

    /**
     * @param array<string, string|int|float|bool> $value Elements.
     * @return self
     */
    public function nin( array $value ) : self
    {
        return $this->addCondition(
            operator: 'nin',
               value: '[' . implode( separator: ',', array: $value ) . ']'
        );
    }

    /**
     * @param array<string, string|int|float|bool> $value Elements.
     * @return self
     */
    public function has( array $value ) : self
    {
        return $this->addCondition(
            operator: 'has',
               value: '[' . implode( separator: ',', array: $value ) . ']'
        );
    }

    /**
     * @param array<string, string|int|float|bool> $value Elements.
     * @return self
     */
    public function nhas( array $value ) : self
    {
        return $this->addCondition(
            operator: 'nhas',
               value: '[' . implode( separator: ',', array: $value ) . ']'
        );
    }

    public function gt( string|int|float $value ) : self
    {
        return $this->addCondition( operator: 'gt', value: $value );
    }

    public function lt( string|int|float $value ) : self
    {
        return $this->addCondition( operator: 'lt', value: $value );
    }

    public function gte( string|int|float $value ) : self
    {
        return $this->addCondition( operator: 'gte', value: $value );
    }

    public function lte( string|int|float $value ) : self
    {
        return $this->addCondition( operator: 'lte', value: $value );
    }

    public function ft( string $value ) : self
    {
        return $this->addCondition( operator: 'ft', value: $value );
    }

    public function ftb( string $value ) : self
    {
        return $this->addCondition( operator: 'ftb', value: $value );
    }


    public function ftq( string $value ) : self
    {
        return $this->addCondition( operator: 'ftq', value: $value );
    }



/* ESCAPE CERTAIN CHARACTERS
----------------------------------------------------------------------------- */

    /**
     * @param string $value Value to escape.
     * @return string Escaped value.
     */
    private static function escape( string $value ) : string
    {
        $string = str_replace( search: '&', replace: '\&', subject: $value );

        return str_replace( search: ':', replace: '\:', subject: $string );
    }
}