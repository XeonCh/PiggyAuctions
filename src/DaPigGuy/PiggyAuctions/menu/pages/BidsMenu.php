<?php

declare(strict_types=1);

namespace DaPigGuy\PiggyAuctions\menu\pages;

use DaPigGuy\PiggyAuctions\auction\Auction;
use DaPigGuy\PiggyAuctions\auction\AuctionBid;
use DaPigGuy\PiggyAuctions\menu\Menu;
use DaPigGuy\PiggyAuctions\menu\utils\MenuUtils;
use DaPigGuy\PiggyAuctions\PiggyAuctions;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\IntTag;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;

class BidsMenu extends Menu
{
    private TaskHandler $taskHandler;

    public function __construct(Player $player)
    {
        $this->taskHandler = PiggyAuctions::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (): void {
            $this->render();
        }), 20);
        parent::__construct($player);
    }

    public function render(): void
    {
        $this->setName(PiggyAuctions::getInstance()->getMessage("menus.view-bids.title"));
        $this->getInventory()->clearAll();

        $auctions = array_filter(array_map(static function (AuctionBid $bid): ?Auction {
            return $bid->getAuction();
        }, PiggyAuctions::getInstance()->getAuctionManager()->getBidsBy($this->player)), function (?Auction $auction): bool {
            return $auction !== null && count($auction->getUnclaimedBidsHeldBy($this->player->getName())) > 0;
        });
        $claimable = array_filter($auctions, function (Auction $auction): bool {
            return $auction->hasExpired();
        });

        MenuUtils::updateDisplayedItems($this, $auctions, 0, 10, 7);
        if (count($claimable) > 1) $this->getInventory()->setItem(21, StringToItemParser::getInstance()->parse("cauldron")->setCustomName(PiggyAuctions::getInstance()->getMessage("menus.claim-all")));
        $this->getInventory()->setItem(22, VanillaItems::ARROW()->setCustomName(PiggyAuctions::getInstance()->getMessage("menus.back")));
        if ($this->player->isOnline()) {
            $this->player->getNetworkSession()->getInvManager()?->syncContents($this->getInventory());
        }
    }

    public function handle(Item $itemClicked, Item $itemClickedWith, SlotChangeAction $action, InvMenuTransaction $transaction): InvMenuTransactionResult
    {
        $newMenu = null;
        switch ($action->getSlot()) {
            case 21:
                foreach (PiggyAuctions::getInstance()->getAuctionManager()->getBidsBy($this->player) as $bid) {
                    $auction = $bid->getAuction();
                    if ($auction !== null && $auction->hasExpired()) {
                        $auction->bidderClaim($this->player);
                    }
                }
                $this->render();
                break;
            case 22:
                $newMenu = new MainMenu($this->player);
                break;
            default:
                $auction = PiggyAuctions::getInstance()->getAuctionManager()->getAuction(($itemClicked->getNamedTag()->getTag("AuctionID") ?? new IntTag(0))->getValue());
                if ($auction !== null) $newMenu = new AuctionMenu($this->player, $auction, function () {
                    (new BidsMenu($this->player))->display();
                });
                break;
        }
        if ($newMenu === null) return $transaction->discard();
        return $transaction->discard()->then(function () use ($newMenu): void {
            $newMenu->display();
        });
    }

    public function close(): void
    {
        parent::close();
        $this->taskHandler->cancel();
    }
}