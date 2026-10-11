<?php

declare(strict_types=1);

namespace Manzadey\Larasentry\Breadcrumbs;

class Breadcrumb
{
    public function __construct(
        public readonly string $category,      // sql | log | http | queue | cache
        public readonly string $message,
        public readonly string $level = 'info', // info | warning | error
        public readonly array $data = [],
        public readonly float $timestamp = 0.0
    ) {
    }

    public function toArray(): array
    {
        $arr = [
            'category'  => $this->category,
            'message'   => $this->message,
            'level'     => $this->level,
            'timestamp' => $this->timestamp > 0.0 ? $this->timestamp : microtime(true),
        ];

        if (!empty($this->data)) {
            $arr['data'] = $this->data;
        }

        return $arr;
    }
}
