<?php

/* This document is meant to follow PSR-1 and PSR-2 php-fig standards
   http://www.php-fig.org/psr/psr-1/
   http://www.php-fig.org/psr/psr-2/ */

/**
 * AutoLoader Imeplementation
 * 
 * This file contains a simple autoloader that is specific to the Phabstractic
 * libraries.  However, it can be used to implement psr-0, prefix based,
 * class mapping.  See AbstractLoader.php
 * 
 * @copyright Copyright 2016 Asher Wolfstein
 * @author Asher Wolfstein <asherwunk@gmail.com>
 * @link http://wunk.me/ The author's URL
 * @link http://wunk.me/programming-projects/phabstractic/ Framework URL
 * @license http://opensource.org/licenses/MIT
 * @package Loader
 * @subpackage Standard
 * 
 */

/**
 * Falcraft Libraries Loader Namespace
 * 
 */
namespace Phabstractic\Loader
{
    require_once(realpath( __DIR__ . '/../') . '/falcraftLoad.php');
    
    $includes = array('/Loader/Resource/AbstractLoader.php',
                      '/Data/Components/Path.php', );
    
    falcraftLoad($includes, __FILE__);

    use Phabstractic\Loader\Resource as LoaderResource;
    use Phabstractic\Data\Components;

    /**
     * AutoLoader Class - Defines standard autoloader for Phabstractic libraries
     * 
     * This class inherits path, module, prefix, and library tracking from
     * Resource/AbstractLoader.  The job of this class is to provide
     * a concrete means of determining how classes are loaded.
     * 
     * This class is inheritable if a different autoloading
     * mechanism or logic must be employed
     * 
     * CHANGELOG
     * 
     * 1.0: Documented AutoLoader - May 5th, 2013
     * 2.0: Refactored and integrated with Primus 2 - October 20th, 2015
     * 3.0: removed default path in realbase
     *      reformatted for inclusion in phabstractic - August 3rd, 2016
     * 
     * @version 3.0
     * 
     */
    class AutoLoader extends LoaderResource\AbstractLoader
    {
        /**
         * Delimiters to also separate apart when parsing out a class
         * 
         * Use for example '_' to comply with PSR-0
         * 
         * This defaults to '\' (the namespace delimiter)
         * 
         * @var array
         * 
         */
        protected $delimiters = array();

        /**
         * Establish the autoloaders defaultDelimiters
         * 
         * In essence resetting the delimiters to only accept namespace delimiters
         * 
         */
        public function defaultDelimiters()
        {
            $this->delimiters = array('\\');
        }

        /**
         * AutoLoader Constructor
         * 
         * This takes in the paths, options, and delimiters
         * 
         * Because this takes delimiters we deal with that in this constructor
         * 
         * @param array $paths The paths passed to AbstractLoader
         * @param array $options The options passed to AbstractLoader
         * @param array $delimiters Additional delimiters such as underscore to split the class names up (each delimiter is an array entry)
         * 
         */
        public function __construct(
            $paths = array(),
            array $options = array(),
            $delimiters = array()
        ) {
            // delimiters go after options for class inheritance compatibility
            
            parent::__construct($paths, $options);
            $this->defaultDelimiters();
            foreach ($delimiters as $delimiter) {
                $this->addDelimiter($delimiter);
            }
            
        }

        /**
         * Retrieve all the delimiters in an array
         * 
         * @return array
         * 
         */
        public function getDelimiters()
        {
            return $this->delimiters;
        }

        /**
         * Add a delimiter to the autoloader
         * 
         * @param string $delimiter To add
         * 
         */
        public function addDelimiter($delimiter)
        {
            $this->delimiters[] = $delimiter;
        }

        /**
         * Remove a delimiter from the autoloader
         * 
         * Pass the actual delimiter such as an underscore
         * 
         * @param string $delimiter The delimiter to remove
         * 
         */
        public function removeDelimiter($delimiter)
        {
            if (($index = array_search($delimiter, $this->delimiters)) !== false) {
                array_splice($this->delimiters, $index, 1);
            }
        }
        
        /**
         * Separate multiple delimiters
         * 
         * @param string $input
         * 
         * @return array
         * 
         */
        private function explodeDelimiters($input)
        {
            // http://stackoverflow.com/questions/2860238/exploding-by-array-of-delimiters
            $udelim = $this->delimiters[0];
            $step = str_replace($this->delimiters, $udelim, $input);
            return explode($udelim, $step);
        }

        /**
         * Autoload a Class
         * 
         * This is the meat of the whole operation.  Everything up until now
         * (AbstractLoader, GenericInterface, Module, Path, etc.) has been
         * constructed for this function to occur.
         * 
         * This function first establishes a base path either from a predefined
         * constant (presumably from the bootstrap), or, in the
         * case that these are not specified, two folders up ( /library/ ).
         * 
         * Then it parses the class string with the appropriate delimiters.
         * 
         * It moves on to parse through the modules, constructing a path,
         * by concatenating relative paths and replacing with absolute paths.
         * 
         * If path remnants are left over, it constructs a NEW path (losing
         * extensions but not prefixes).  This is important, if you want
         * extensions the path object must be in the terminating module.
         * 
         * It then checks file existence with prefixes, and then it processes
         * through independent paths (like an include path string)
         * 
         * NOTE: This function uses require_once
         * 
         * @param string $class The fully qualified class name to load
         * 
         */
        public function autoload($class)
        {
            $realBasePath = '';
            
            if (defined( 'PHABSTRACTIC_APPLICATION_PATH')) {
                $realBasePath = PHABSTRACTIC_APPLICATION_PATH;
            } else {
                $realBasePath = realpath(
                    __DIR__ . DIRECTORY_SEPARATOR . '..' .
                    DIRECTORY_SEPARATOR . '..' .
                    DIRECTORY_SEPARATOR
                );
            }

            $keys = $this->explodeDelimiters($class);

            $classToLoad = array_pop($keys);
            
            if (!$keys) {
                // This is a built in non namespaced class (or should be)
                return;
            }

            $basePath = '';
            
            // try modules first
            
            $currentModule = &$this->getNamespaceModule('');
            
            if ($currentModule->isSubModuleByIdentifier($keys[0])) {
                $currentModule = &$currentModule->getModuleByIdentifierReference($keys[0]);
                
                while ($keys) {
                    $path = $currentModule->getPath();
                    
                    if ($path->isRelative()) {
                        $basePath .= DIRECTORY_SEPARATOR . $path->getPath();
                    } else {
                        $basePath = $path->getPath();
                    }
                    
                    array_shift($keys);
                    
                    if ($keys) {
                        if ($currentModule->isSubModuleByIdentifier($keys[0])) {
                            $currentModule =
                                &$currentModule->getModuleByIdentifierReference($keys[0]);
                        } else {
                            break;
                        }
                    }
                }
                
                if ($basePath[0] == DIRECTORY_SEPARATOR) {
                    $basePath = substr($basePath, 1);
                }
                
                $path = '';
            
                if (count($keys)) {
                    $path = implode(DIRECTORY_SEPARATOR, $keys);
                }
                
                $path = new Components\Path(
                    $path,
                    array($this->conf->file_extension),
                    array('strict' => $this->conf->strict)
                );
                
                $prefixes = $this->getPrefixes($basePath);
                
                $unprefixedClassToLoad = '';
                
                if ($prefixes) {
                    foreach ($prefixes as $prefix) {
                        if (strpos($classToLoad, $prefix ) === 0) {
                            $unprefixedClassToLoad = substr(
                                $classToLoad,
                                strlen($prefix)
                            );
                        }
                        
                    }
                    
                }
                
                if ($unprefixedClassToLoad &&
                        ($retPath = $path->isFilename(
                            $unprefixedClassToLoad,
                            $realBasePath . DIRECTORY_SEPARATOR . $basePath))
                ) {
                    require_once($retPath);
                    return;
                } elseif ($retPath = $path->isFilename(
                        $classToLoad,
                        $realBasePath . DIRECTORY_SEPARATOR . $basePath)) {
                    require_once($retPath);
                    return;
                }
            }
            
            // now try paths
            
            $keys = $this->explodeDelimiters($class);
            $classToLoad = array_pop($keys);
            $classPath = implode(DIRECTORY_SEPARATOR, $keys) . DIRECTORY_SEPARATOR;
            
            foreach ($this->paths as $path) {
                $prefixes = $this->getPrefixes($path);
                
                $unprefixedClassToLoad = '';
            
                if ($prefixes) {
                    foreach ($prefixes as $prefix) {
                        if (strpos($classToLoad, $prefix ) === 0) {
                            $unprefixedClassToLoad = substr(
                                $classToLoad,
                                strlen($prefix)
                            );
                        }
                        
                    }
                    
                }
                
                $path = new Components\Path(
                    $path->getPath() . DIRECTORY_SEPARATOR . $classPath,
                    $path->getExtensions(),
                    array('strict' => $this->conf->strict)
                );
                
                if ($unprefixedClassToLoad &&
                        ($retPath = $path->isFilename(
                            $unprefixedClassToLoad,
                            $realBasePath))
                ) {
                    require_once($retPath);
                    return;
                } elseif ($retPath = $path->isFilename(
                        $classToLoad,
                        $realBasePath)
                ) {
                    require_once($retPath);
                    return;
                }
            }

            return;
        }
        
        /**
         * Debug Info (var_dump)
         * 
         * Display debug info
         * 
         * Requires PHP 5.6+
         * 
         */
        public function __debugInfo()
        {
            return [
                'options' => array('strict' => $this->conf->strict,
                                   'auto_register' => $this->conf->auto_register,
                                   'file_extension' => $this->conf->file_extension,),
                'paths' => $this->paths,
                'prefixes' => $this->prefixes,
                'libraries' => $this->libraries,
            ];
        }
        
    }
}
