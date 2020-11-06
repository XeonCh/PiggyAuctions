<?php

declare(strict_types=1);

namespace DaPigGuy\PiggyAuctions\menu\pages;

use DaPigGuy\PiggyAuctions\menu\Menu;
use DaPigGuy\PiggyAuctions\PiggyAuctions;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;

class MainMenu extends Menu
{
    public function handle(Item $itemClicked, Item $itemClickedWith, SlotChangeAction $action, InvMenuTransaction $transaction): InvMenuTransactionResult
    {
        $newMenu = null;
        switch ($action->getSlot()) {
            case 11:
                $newMenu = new AuctionBrowserMenu($this->player);
                break;
            case 13:
                $newMenu = new BidsMenu($this->player);
                break;
            case 15:
                if (count(PiggyAuctions::getInstance()->getAuctionManager()->getAuctionsHeldBy($this->player)) < 1) {
                    $newMenu = new AuctionCreatorMenu($this->player);
                    break;
                }
                $newMenu = new AuctionManagerMenu($this->player);
                break;
            case 26:
                $newMenu = new StatsMenu($this->player);
                break;
        }
        if ($newMenu === null) return $transaction->discard();
        return $transaction->discard()->then(function () use ($newMenu): void {
            $newMenu->display();
        });
    }

    public function render(): void
    {
        $this->setName(PiggyAuctions::getInstance()->getMessage("menus.main-menu.title"));
        $this->getInventory()->setContents([
            11 => ItemFactory::get(ItemIds::GOLD_BLOCK)->setCustomName(PiggyAuctions::getInstance()->getMessage("menus.main-menu.browse-auctions")),
            13 => ItemFactory::get(ItemIds::GOLDEN_CARROT)->setCustomName(PiggyAuctions::getInstance()->getMessage("menus.main-menu.view-bids")),
            15 => ItemFactory::get(ItemIds::GOLDEN_HORSE_ARMOR)->setCustomName(PiggyAuctions::getInstance()->getMessage("menus.main-menu.manage-auctions")),
            26 => ItemFactory::get(ItemIds::MAP)->setCustomName(PiggyAuctions::getInstance()->getMessage("menus.main-menu.auction-stats"))
        ]);
    }
}