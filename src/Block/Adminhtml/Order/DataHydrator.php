<?php

/**
 * Copyright Shopgate Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author    Shopgate Inc, 804 Congress Ave, Austin, Texas 78701 <interfaces@shopgate.com>
 * @copyright Shopgate Inc
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */
declare(strict_types=1);

namespace Shopgate\Import\Block\Adminhtml\Order;

class DataHydrator
{

    /**
     * @var array
     */
    private $data;

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Removes data
     *
     * @param array $whitelist
     * @return $this
     */
    public function filterWhitelist(array $whitelist): self
    {
        $this->data = array_intersect_key($this->getData(), array_flip($whitelist));

        return $this;
    }

    /**
     * Data retrieve
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Removes null|empty array items
     *
     * @return $this
     */
    public function removeEmpty(): self
    {
        $this->data = array_filter($this->getData(), function ($value) {
            return $value !== null && $value !== '';
        });

        return $this;
    }

    /**
     * Cleans keys into pretty-print format
     *
     * @return $this
     */
    public function readableKeys(): self
    {
        $this->data = $this->cleanKeys($this->getData());

        return $this;
    }

    /**
     * Takes in a list & transforms keys into pretty print
     *
     * @param array $data
     * @return array
     */
    private function cleanKeys(array $data): array
    {
        foreach ($data as $key => $value) {
            $data[$this->cleanKey($key)] = is_array($value) ? $this->cleanKeys($value) : $value;
            unset($data[$key]);
        }
        return $data;
    }

    /**
     * Makes the value print ready
     *
     * @param string $text
     * @return string
     */
    private function cleanKey(string $text): string
    {
        return ucwords(str_replace('_', ' ', $text));
    }
}
