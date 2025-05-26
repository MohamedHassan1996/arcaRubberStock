<?php
    
namespace Core\Contracts;

interface HasMiddleware
{
    public static function middleware(): array;
}
