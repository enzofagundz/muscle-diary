<?php

namespace App\Support;

class ImportReport
{
    /**
     * @param  list<string>  $created
     * @param  list<string>  $skipped
     * @param  list<string>  $problems
     */
    public function __construct(
        public array $created = [],
        public array $skipped = [],
        public array $problems = [],
    ) {}
}
