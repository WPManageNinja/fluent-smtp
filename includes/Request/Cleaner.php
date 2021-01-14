<?php

namespace FluentMail\Includes\Request;

trait Cleaner
{
    /**
     * Clean up the request data.
     *
     * @param  array $data
     * @return array
     */
    public function clean($data)
    {
        return $this->cleanArray($data);
    }

    /**
     * Clean the data in the given array.
     *
     * @param  array $data
     * @return array
     */
    protected function cleanArray(array $data)
    {
        return array_map(function ($value) {
            return $this->cleanValue($value);
        }, $data);
    }

    /**
     * Clean the given value.
     *
     * @param  mixed $value
     * @return mixed
     */
    protected function cleanValue($value)
    {
        if (is_array($value)) {
            return $this->cleanArray($value);
        }

        return $this->transform($value);
    }

    /**
     * Transform the given value.
     *
     * @param  mixed $value
     * @return mixed
     */
    protected function transform($value)
    {
        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                $value = null;
            }
        }

        return $value;
    }
}
