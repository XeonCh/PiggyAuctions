<?php

declare(strict_types=1);

namespace DaPigGuy\PiggyAuctions\statistics;

use DaPigGuy\PiggyAuctions\PiggyAuctions;
use pocketmine\Player;

class PlayerStatistics implements \JsonSerializable
{
    /** @var Player */
    private $player;
    /** @var int[] */
    private $statistics;

    public function __construct(Player $player, array $statistics)
    {
        $this->player = $player;
        $this->statistics = $statistics;
    }

    public function getStatistic(string $name): int
    {
        return $this->statistics[$name] ?? 0;
    }

    public function setStatistic(string $name, int $value): void
    {
        $this->statistics[$name] = $value;
        PiggyAuctions::getInstance()->getStatsManager()->saveStatistics($this->player);
    }

    public function incrementStatistic(string $name, int $amount = 1): void
    {
        if (!isset($this->statistics[$name])) $this->statistics[$name] = 0;
        $this->statistics[$name] += $amount;
        PiggyAuctions::getInstance()->getStatsManager()->saveStatistics($this->player);
    }

    public function jsonSerialize(): array
    {
        return $this->statistics;
    }
}