<?php

declare(strict_types = 1);

namespace DaPigGuy\PiggyAuctions\auction;

use DaPigGuy\PiggyAuctions\events\AuctionExpireEvent;
use DaPigGuy\PiggyAuctions\events\AuctionLoadEvent;
use DaPigGuy\PiggyAuctions\PiggyAuctions;
use pocketmine\item\{Item, StringToItemParser};
use pocketmine\player\Player;
use pocketmine\nbt\JsonNbtParser;
use pocketmine\nbt\tag\CompoundTag;

class AuctionManager
{
    /** @var Auction[] */
    private array $auctions = [];
    private bool $auctionsLoaded = false;

    public function __construct(private PiggyAuctions $plugin) {}

    public function init(): void
    {
        $this->plugin->getDatabase()->executeGeneric("piggyauctions.init");
        $this->plugin->getDatabase()->executeSelect("piggyauctions.load", [], function (array $rows): void {
            $this->auctionsLoaded = true;
            foreach ($rows as $row) {
                $itemArray = json_decode($row["item"], true);
                $this->auctions[$row["id"]] = new Auction(
                    $row["id"],
                    $row["auctioneer"],
                    self::jsonDeserialize($itemArray),
                    $row["startdate"],
                    $row["enddate"],
                    (bool)$row["claimed"],
                    array_map(static function (array $bidData) use ($row) {
                        return new AuctionBid($row["id"], $bidData["bidder"], $bidData["bidamount"], $bidData["timestamp"]);
                    }, json_decode($row["claimed_bids"], true)),
                    $row["starting_bid"],
                    array_map(static function (array $bidData) use ($row) {
                        return new AuctionBid($row["id"], $bidData["bidder"], $bidData["bidamount"], $bidData["timestamp"]);
                    }, json_decode($row["bids"], true))
                );
            }
        });
    }

    /**
    * @return Auction[]
    */
    public function getAuctions(): array
    {
        return $this->auctions;
    }

    public function areAuctionsLoaded(): bool
    {
        return $this->auctionsLoaded;
    }

    public function getAuction(int $id): ?Auction
    {
        return $this->auctions[$id] ?? null;
    }

    /**
    * @return Auction[]
    */
    public function getAuctionsHeldBy(Player|string $player): array
    {
        if ($player instanceof Player) $player = $player->getName();
        return array_filter($this->auctions, static function (Auction $auction) use ($player): bool {
            return strtolower($auction->getAuctioneer()) === strtolower($player);
        });
    }

    /**
    * @return Auction[]
    */
    public function getActiveAuctionsHeldBy(Player|string $player): array
    {
        if ($player instanceof Player) $player = $player->getName();
        return array_filter($this->auctions, static function (Auction $auction) use ($player): bool {
            return strtolower($auction->getAuctioneer()) === strtolower($player) && !$auction->hasExpired();
        });
    }

    /**
    * @return Auction[]
    */
    public function getActiveAuctions(): array
    {
        return array_filter($this->auctions, static function (Auction $auction): bool {
            return !$auction->hasExpired();
        });
    }

    /**
    * @return AuctionBid[]
    */
    public function getBids(): array
    {
        $bids = [];
        foreach ($this->auctions as $auction) $bids = array_merge($bids, $auction->getBids());
        return $bids;
    }

    /**
    * @return AuctionBid[]
    */
    public function getBidsBy(Player|string $player): array
    {
        if ($player instanceof Player) $player = $player->getName();
        return array_filter($this->getBids(), static function (AuctionBid $bid) use ($player): bool {
            return strtolower($bid->getBidder()) === strtolower($player);
        });
    }
    
    public static function jsonDeserialize(array $data): ?Item {
        $name = $data["id"];
        $count = (int) $data["count"];
        $tag = $data["tag"];
        $itemName = strtolower(str_replace(" ", "_", (string)$name));
        $item = StringToItemParser::getInstance()->parse($itemName);
        $item->setCount($count);

        if($tag !== null) {
         $item->setNamedTag(unserialize($tag));
        }

        return $item;
    }

    public static function jsonSerialize($item) {
        if($item->hasEnchantments()){
        $tag = serialize($item->getNamedTag());
        }else{
        $tag = null;
        }
        $itemArray = [
            "id" => $item->getVanillaName(),
            "count" => $item->getCount(),
            "tag" => $tag
            // Tambahkan properti lain sesuai kebutuhan
        ];
        return json_encode($itemArray);
    }
    
    public function addAuction(string $auctioneer, Item $item, int $startDate, int $endDate, int $startingBid): void
    {
        $this->plugin->getDatabase()->executeInsert("piggyauctions.add", [
            "auctioneer" => $auctioneer,
            "item" => self::jsonSerialize($item),
            "startdate" => $startDate,
            "enddate" => $endDate,
            "claimed" => 0,
            "claimed_bids" => json_encode([]),
            "starting_bid" => $startingBid,
            "bids" => json_encode([])
        ], function (int $id) use ($auctioneer, $item, $startDate, $endDate, $startingBid) {
            $this->auctions[$id] = new Auction($id, $auctioneer, $item, $startDate, $endDate, false, [], $startingBid, []);
            (new AuctionLoadEvent($this->auctions[$id]))->call();
        });
    }

    public function updateAuction(Auction $auction): void
    {
        $this->plugin->getDatabase()->executeChange("piggyauctions.update", [
            "id" => $auction->getId(),
            "claimed" => (int)$auction->isClaimed(),
            "claimed_bids" => json_encode(array_map(static function (AuctionBid $bid) {
                return ["bidder" => $bid->getBidder(), "bidamount" => $bid->getBidAmount(), "timestamp" => $bid->getTimestamp()];
            }, $auction->getclaimedBids())),
            "bids" => json_encode(array_map(static function (AuctionBid $bid) {
                return ["bidder" => $bid->getBidder(), "bidamount" => $bid->getBidAmount(), "timestamp" => $bid->getTimestamp()];
            }, $auction->getBids()))
        ]);
    }

    public function removeAuction(Auction $auction): void
    {
        unset($this->auctions[$auction->getId()]);
        (new AuctionExpireEvent($auction))->call();
        $this->plugin->getDatabase()->executeChange("piggyauctions.remove", ["id" => $auction->getId()]);
    }
}
