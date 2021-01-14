<?php

namespace FluentMail\Includes\Request;

trait FileHandler
{
    /**
     * Prepares HTTP files for Request
     *
     * @param array $files
     *
     * @return array
     */
    public function prepareFiles($files = [])
    {
        foreach ($files as $key => &$file) {
            $file = $this->convertFileInformation($file);
        }

        return $files;
    }

    /**
     * @taken from \Symfony\Component\HttpFoundation\FileBag
     *
     * Converts uploaded files to UploadedFile instances.
     *
     * @param array|File $file A (multi-dimensional) array of uploaded file information
     *
     * @return File[]|File|null A (multi-dimensional) array of File instances
     */
    protected function convertFileInformation($file)
    {
        $fileKeys = array('error', 'name', 'size', 'tmp_name', 'type');

        if ($file instanceof File) {
            return $file;
        }

        $file = $this->fixPhpFilesArray($file);

        if (is_array($file)) {
            $keys = array_keys($file);
            sort($keys);

            if ($keys == $fileKeys) {
                if (UPLOAD_ERR_NO_FILE == $file['error']) {
                    $file = null;
                } else {
                    $file = new File($file['tmp_name'], $file['name'], $file['type'], $file['size'], $file['error']);
                }
            } else {
                $file = array_map(array($this, 'convertFileInformation'), $file);
                if (array_keys($keys) === $keys) {
                    $file = array_filter($file);
                }
            }
        }

        return $file;
    }

    /**
     * @taken from \Symfony\Component\HttpFoundation\FileBag
     *
     * Fixes a malformed PHP $_FILES array.
     *
     * PHP has a bug that the format of the $_FILES array differs, depending on
     * whether the uploaded file fields had normal field names or array-like
     * field names ("normal" vs. "parent[child]").
     *
     * This method fixes the array to look like the "normal" $_FILES array.
     *
     * It's safe to pass an already converted array, in which case this method
     * just returns the original array unmodified.
     *
     * @return array
     */
    protected function fixPhpFilesArray($data)
    {
        $fileKeys = array('error', 'name', 'size', 'tmp_name', 'type');

        if (! is_array($data)) {
            return $data;
        }

        $keys = array_keys($data);
        sort($keys);

        if ($fileKeys != $keys || ! isset($data['name']) || ! is_array($data['name'])) {
            return $data;
        }

        $files = $data;
        foreach ($fileKeys as $k) {
            unset($files[$k]);
        }

        foreach ($data['name'] as $key => $name) {
            $files[$key] = $this->fixPhpFilesArray(array(
                'error'    => $data['error'][$key],
                'name'     => $name,
                'type'     => $data['type'][$key],
                'tmp_name' => $data['tmp_name'][$key],
                'size'     => $data['size'][$key],
            ));
        }

        return $files;
    }
}
