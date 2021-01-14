<?php

namespace FluentMail\Includes\Support\Contracts;

interface FileInterface
{
    /**
     * Returns whether the file was uploaded successfully.
     *
     * @return bool
     */
    public function isValid();

    /**
     * Gets the path without filename
     *
     * @return string
     */
    public function getPath();

    /**
     * Take an educated guess of the file's extension.
     *
     * @return mixed|null
     */
    public function guessExtension();

    /**
     * Returns the original file extension.
     *
     * @return string
     */
    public function getClientOriginalExtension();
}
