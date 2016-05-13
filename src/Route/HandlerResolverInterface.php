<?php

namespace Buuum;

interface HandlerResolverInterface {

    /**
     * Create an instance of the given handler.
     *
     * @param $handler
     * @return array
     */
    public function resolve($handler);

    /**
     * if true errors are parser by resolveErrors
     * @return boolean
     */
    public function parseErrors();

    /**
     * @param $type_error
     * @param $request
     * @return mixed
     */
    public function resolveErrors($type_error, $request);
}