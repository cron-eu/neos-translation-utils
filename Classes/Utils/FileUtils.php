<?php

namespace CRON\NeosTranslationUtils\Utils;

class FileUtils
{

    /**
     * List entries (files and directories) in a directory using a pattern.
     *
     * @param string $directoryPath The directory which entries should be listed.
     * @param string $pattern Pattern to filter the entries.
     * @return array Absolute paths of the entries.
     */
    private function listDirectoryEntries($directoryPath, $pattern)
    {
        return glob(rtrim($directoryPath, '/') . '/' . ($pattern ? $pattern : '*'));
    }

    /**
     * List files in a directory using a pattern.
     *
     * @param string $directoryPath The directory which files should be listed.
     * @param string $pattern Pattern to filter the files.
     * @return array Absolute paths of the entries.
     */
    public function listFilesInDirectory($directoryPath, $pattern)
    {
        $files = $this->listDirectoryEntries($directoryPath, $pattern);

        return array_filter($files, function($file) {
            $path = realpath($file);

            return $path && !is_dir($path);
        });
    }

    /**
     * List directories in a directory using a pattern.
     *
     * @param string $directoryPath The directory which directories should be listed.
     * @param string $pattern Pattern to filter the directories.
     * @return array Absolute paths of the directories.
     */
    public function listDirectoriesInDirectory($directoryPath, $pattern)
    {
        $files = $this->listDirectoryEntries($directoryPath, $pattern);
        return array_filter($files, function($file) {
            $path = realpath($file);

            return $path && is_dir($path);
        });
    }

    /**
     * Recursive function to walk the pattern parts.
     *
     * @param string $currentDir
     * @param string[] $patternParts
     * @param int $idx
     * @return string[]
     */
    private function doListFiles($currentDir, $patternParts, $idx)
    {
        $currentDir = rtrim($currentDir, '/');

        $matchingFiles = [];

        if (count($patternParts) <= $idx) {
            return $matchingFiles;
        }
        $patternPart = $patternParts[$idx];

        if ($patternPart == '**') {
            // get the matching files from the current directory
            $matchingFiles = array_merge($matchingFiles, $this->doListFiles($currentDir, $patternParts, $idx + 1));

            // get the matching files from the sub-directories
            $subDirs = $this->listDirectoriesInDirectory($currentDir, '*');

            foreach ($subDirs as $subDir) {
                $matchingFiles = array_merge($matchingFiles, $this->doListFiles($subDir, $patternParts, $idx));
            }
        } else if (str_contains($patternPart, '*')) {
            $files = $this->listFilesInDirectory($currentDir, $patternPart);

            foreach ($files as $file) {
                $matchingFiles[] = $file;
            }
        } else {
            $fileOrDir = $currentDir . '/' . $patternPart;
            if (is_dir($fileOrDir)) {
                $matchingFiles = array_merge($matchingFiles, $this->doListFiles($fileOrDir, $patternParts, $idx + 1));
            } else {
                // only consider the last part of the pattern a file
                if (file_exists($fileOrDir) && (($idx + 1) >= count($patternParts))) {
                    $matchingFiles[] = $fileOrDir;
                }
            }
        }

        return $matchingFiles;
    }


    /**
     * List all files based on the base path that fit the given pattern.
     *
     * @param string $basePath Absolute base path.
     * @param string $pattern
     * @return string[]|null
     */
    public function globFiles($basePath, $pattern)
    {
        if (!is_dir($basePath)) {
            // not a directory!
            return null;
        }

        return $this->doListFiles($basePath, explode('/', $pattern), 0);
    }

    /**
     * Creates a directory recursively.
     *
     * @param string $directoryPath Absolute path for the directory that should be created.
     * @return boolean True if the directory already existed or was created successfully, false otherwise.
     */
    public function createDirectoryIfNotExists($directoryPath)
    {
        if (is_file($directoryPath)) {
            return false;
        }
        if (is_dir($directoryPath)) {
            return true;
        }

        return mkdir($directoryPath, 0777, true);
    }

    /**
     * Creates an empty file.
     *
     * @param string $filePath Absolute path for the file that should be created.
     * @return boolean True if the file already existed or was created successfully, false otherwise.
     */
    public function createFileIfNotExists($filePath)
    {
        if (is_file($filePath)) {
            return true;
        }

        return touch($filePath);
    }

    /**
     * Writes the given content to a file.
     *
     * @param string $filePath Absolute path for the file that should be written to.
     * @param string $content Content to be written to the file.
     *
     * @return boolean True if the content was successfully written, false otherwise.
     */
    public function writeFile($filePath, $content)
    {
        return file_put_contents($filePath, $content);
    }
}
