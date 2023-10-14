<?php declare(strict_types=1);

namespace Shopgate\Import\Block\Adminhtml\Order;

class DataHydrator
{

    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function filterWhitelist(array $whitelist): self
    {
        $this->data = array_intersect_key($this->getData(), array_flip($whitelist));

        return $this;
    }

    public function removeEmpty(): self
    {
        $this->data = array_filter($this->getData(), function ($value) {
            return !is_null($value) && $value !== '';
        });

        return $this;
    }

    public function readableKeys(): self
    {
        $this->data = $this->cleanKeys($this->getData());

        return $this;
    }

    private function cleanKeys(array $data): array
    {
        foreach ($data as $key => $value) {
            $data[$this->cleanKey($key)] = is_array($value) ? $this->cleanKeys($value) : $value;
            unset($data[$key]);
        }
        return $data;
    }

    private function cleanKey(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }

    public function getData(): array
    {
        return $this->data;
    }

}
