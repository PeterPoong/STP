<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class UniqueInArray implements Rule
{
    protected $attribute;
    protected $duplicates = [];

    public function __construct($attribute)
    {
        $this->attribute = $attribute;
    }

    public function passes($attribute, $value)
    {
        $values = array_column($value, $this->attribute);
        $this->duplicates = array_diff_assoc($values, array_unique($values));
        return empty($this->duplicates);
    }

    public function message()
    {
        return 'Duplicate subject name';
    }
}
