<?php

declare(strict_types=1);

namespace Manzadey\Larasentry\Breadcrumbs;

class BreadcrumbRecorder
{
    protected static ?self $instance = null;

    /**
     * @var Breadcrumb[]
     */
    protected array $buffer = [];
    protected int $maxItems = 30;

    public function __construct(int $maxItems = 30)
    {
        $this->maxItems = max(5, min(100, $maxItems));
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            $max = function_exists('config') ? (int)config('larasentry.max_breadcrumbs', 30) : 30;
            self::$instance = new self($max);
        }

        return self::$instance;
    }

    public function record(Breadcrumb $breadcrumb): void
    {
        $this->buffer[] = $breadcrumb;

        // Кольцевой FIFO-буфер: вытесняем самые старые элементы при превышении лимита
        if (count($this->buffer) > $this->maxItems) {
            array_shift($this->buffer);
        }
    }

    /**
     * @return array[]
     */
    public function toArray(): array
    {
        return array_map(static fn (Breadcrumb $b) => $b->toArray(), $this->buffer);
    }

    public function clear(): void
    {
        $this->buffer = [];
    }
}
