<?php

declare(strict_types=1);

namespace brokiem\simplepets\manager;

use brokiem\simplepets\entity\pets\base\BasePet;
use brokiem\simplepets\entity\pets\base\CustomPet;
use brokiem\simplepets\entity\pets\GoatPet;
use brokiem\simplepets\entity\pets\WolfPet;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\player\Player;
use pocketmine\world\World;

final class PetManager {

    private array $pet_list = [
        "GoatPet" => GoatPet::class,
        "WolfPet" => WolfPet::class
    ];

    private array $registered_pets = [];

    public function __construct() {
        foreach ($this->pet_list as $type => $class) {
            self::registerEntity($class, [$type]);
            $this->registerPet($class);
        }
    }

    public static function registerEntity(string $entityClass, array $saveNames = []): void {
        if (!class_exists($entityClass)) {
            throw new \RuntimeException("Class $entityClass not found.");
        }

        $refClass = new \ReflectionClass($entityClass);
        if (is_a($entityClass, BasePet::class, true) || is_a($entityClass, CustomPet::class, true) and !$refClass->isAbstract()) {
            if (is_a($entityClass, CustomPet::class, true)) {
                EntityFactory::getInstance()->register($entityClass, function(World $world, CompoundTag $nbt) use ($entityClass): Entity {
                    return new $entityClass(EntityDataHelper::parseLocation($nbt, $world), Human::parseSkinNBT($nbt), $nbt);
                }, array_merge([$entityClass], $saveNames));
            } else {
                EntityFactory::getInstance()->register($entityClass, function(World $world, CompoundTag $nbt) use ($entityClass): Entity {
                    return new $entityClass(EntityDataHelper::parseLocation($nbt, $world), $nbt);
                }, array_merge([$entityClass], $saveNames));
            }
        }
    }

    public function registerPet(string $class): void {
        if (!class_exists($class)) {
            throw new \RuntimeException("Class $class not found.");
        }

        $refClass = new \ReflectionClass($class);
        if (is_a($class, BasePet::class, true) || is_a($class, CustomPet::class, true) and !$refClass->isAbstract()) {
            $this->registered_pets[] = $class;
        }
    }

    public function getRegisteredPets(): array {
        return $this->registered_pets;
    }

    public function spawnPet(Player $owner, string $petType, string $petName, float $petSize = 1): void {
        $nbt = $this->createBaseNBT($owner->getPosition());
        $pet = $this->createEntity($petType, $owner->getLocation(), $nbt);

        $pet?->setPetName($petName);
        $pet?->setPetSize($petSize);

        $pet?->spawnToAll();
    }

    /**
     * Helper function which creates minimal NBT needed to spawn an entity.
     */
    public function createBaseNBT(Vector3 $pos, ?Vector3 $motion = null, float $yaw = 0.0, float $pitch = 0.0): CompoundTag {
        return CompoundTag::create()
            ->setTag("Pos", new ListTag([
                new DoubleTag($pos->x),
                new DoubleTag($pos->y),
                new DoubleTag($pos->z)
            ]))
            ->setTag("Motion", new ListTag([
                new DoubleTag($motion !== null ? $motion->x : 0.0),
                new DoubleTag($motion !== null ? $motion->y : 0.0),
                new DoubleTag($motion !== null ? $motion->z : 0.0)
            ]))
            ->setTag("Rotation", new ListTag([
                new FloatTag($yaw),
                new FloatTag($pitch)
            ]));
    }

    public function createEntity(string $type, Location $location, CompoundTag $nbt): null|BasePet|CustomPet {
        if (isset($this->registered_pets[$type])) {
            /** @var BasePet|CustomPet $class */
            $class = $this->registered_pets[$type];

            if (is_a($class, BasePet::class, true)) {
                return new $class($location, $nbt);
            }

            if (is_a($class, CustomPet::class, true)) {
                return new $class($location, Human::parseSkinNBT($nbt), $nbt);
            }
        }

        return null;
    }
}