<?php
/**
 * This file is part of OXID eSales Testing Library.
 *
 * OXID eSales Testing Library is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * OXID eSales Testing Library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OXID eSales Testing Library. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.oxid-esales.com
 * @copyright (C) OXID eSales AG 2003-2014
 */
namespace OxidEsales\TestingLibrary\Services\ShopObjectConstructor\Constructor;

/**
 * Class CallerFactory
 */
class ConstructorFactory
{
    /**
     * @param string $sClassName
     *
     * @return ObjectConstructor
     */
    public function getConstructor($sClassName)
    {
        $constructorClassName = $this->formConstructorClass($sClassName);
        if (!class_exists($constructorClassName)) {
            $constructorClassName = $this->formConstructorClass('Object');
        }

        return new $constructorClassName($sClassName);
    }

    /**
     * @param string $className
     *
     * @return bool|string
     */
    protected function formConstructorClass($className)
    {
        $sConstructorClass = $className . "Constructor";
        return "OxidEsales\\TestingLibrary\\Services\\ShopObjectConstructor\\Constructor\\$sConstructorClass";
    }
}
