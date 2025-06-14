<?php

namespace Tourze\DoctrineAutoBindBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;

/**
 * 测试用实体
 */
#[Entity]
class TestEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    private int $id;
    
    #[Column(type: 'string', length: 255)]
    private string $name;
    
    public function getId(): int
    {
        return $this->id;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function setName(string $name): void
    {
        $this->name = $name;
    }
} 