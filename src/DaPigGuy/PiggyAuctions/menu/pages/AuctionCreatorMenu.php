<?php

declare(strict_types=1);

namespace DaPigGuy\PiggyAuctions\menu\pages;

use Closure;
use DaPigGuy\PiggyAuctions\events\AuctionStartEvent;
use DaPigGuy\PiggyAuctions\menu\Menu;
use DaPigGuy\PiggyAuctions\PiggyAuctions;
use DaPigGuy\PiggyAuctions\utils\Utils;
use jojoe77777\FormAPI\CustomForm;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class AuctionCreatorMenu extends Menu
{
    protected string $inventoryIdentifier = InvMenuTypeIds::TYPE_DOUBLE_CHEST;
    private Item $item;
    private int $startingBid = 50;
    private int $duration = 7200;

    public function __construct(Player $player)
    {
        $this->item = VanillaItems::AIR();
        parent::__construct($player);
    }

    public function setItem(Item $item): void
    {
        $this->item = $item;
        $this->render();
    }

    public function handle(Item $itemClicked, Item $itemClickedWith, SlotChangeAction $action, InvMenuTransaction $transaction): InvMenuTransactionResult
    {
        switch ($action->getSlot()) {
            case 13:
                return $transaction->continue();
            case 29:
                if ($this->item->getTypeId() !== 0) {
                    $this->setInventoryCloseListener(null);
                    (new ConfirmationMenu(
                        $this->player,
                        PiggyAuctions::getInstance()->getMessage("menus.auction-confirmation.title"),
                        (clone $this->item)->setCustomName(PiggyAuctions::getInstance()->getMessage("menus.auction-confirmation.auctioning", ["{ITEM}" => $this->item->getName()])),
                        PiggyAuctions::getInstance()->getMessage("menus.auction-confirmation.confirm", ["{ITEM}" => $this->item->getName(), "{AMOUNT}" => $this->item->getCount(), "{MONEY}" => $this->startingBid]),
                        PiggyAuctions::getInstance()->getMessage("menus.auction-confirmation.cancel"),
                        function (bool $confirmed): void {
                            if ($confirmed) {
                                $this->getInventory()->clear(13);
                                $ev = new AuctionStartEvent($this->player, $this->item, time(), time() + $this->duration, $this->startingBid);
                                $ev->call();
                                if (!$ev->isCancelled()) {
                                    PiggyAuctions::getInstance()->getStatsManager()->getStatistics($this->player)->incrementStatistic("auctions_created");
                                    PiggyAuctions::getInstance()->getAuctionManager()->addAuction(...$ev->getAuctionData());
                                    (new AuctionManagerMenu($this->player))->display();
                                    return;
                                }
                            }
                            $this->setInventoryCloseListener(Closure::fromCallable([$this, "close"]));
                            $this->display();
                        }
                    ))->display();
                }
                break;
            case 31:
                $this->setInventoryCloseListener(null);
                $this->onClose($this->player);
                $this->setInventoryCloseListener(Closure::fromCallable([$this, "close"]));
                return $transaction->discard()->then(function (): void {
                    $form = new CustomForm(function (Player $player, ?array $data = null): void {
                        if (isset($data[0]) && is_numeric($data[0]) && (int)$data[0] > 0) {
                            $this->startingBid = (int)$data[0] > ($limit = PiggyAuctions::getInstance()->getConfig()->getNested("auctions.limits.starting-bid", 2147483647)) ? $limit : (int)$data[0];
                        }
                        $this->render();
                        $this->display();
                    });
                    $form->setTitle(PiggyAuctions::getInstance()->getMessage("forms.create-auction.title"));
                    $form->addInput(PiggyAuctions::getInstance()->getMessage("forms.create-auction.starting-bid"));
                    $this->player->sendForm($form);
                });
            case 33:
                $this->setInventoryCloseListener(null);
                $this->onClose($this->player);
                $this->setInventoryCloseListener(Closure::fromCallable([$this, "close"]));
                return $transaction->discard()->then(function (): void {
                    $form = new CustomForm(function (Player $player, ?array $data = null): void {
                        if (isset($data[0]) && is_numeric($data[0]) && (int)$data[0] > 0) {
                            $this->duration = (int)$data[0] > ($limit = PiggyAuctions::getInstance()->getConfig()->getNested("auctions.limits.duration", 1209600)) ? $limit : (int)$data[0];
                        }
                        $this->render();
                        $this->display();
                    });
                    $form->setTitle(PiggyAuctions::getInstance()->getMessage("forms.create-auction.title"));
                    $form->addInput(PiggyAuctions::getInstance()->getMessage("forms.create-auction.duration"));
                    $this->player->sendForm($form);
                });
            case 49:
                if (count(PiggyAuctions::getInstance()->getAuctionManager()->getAuctionsHeldBy($this->player)) < 1) {
                    (new MainMenu($this->player))->display();
                    break;
                }
                return $transaction->discard()->then(function (): void {
                    (new AuctionManagerMenu($this->player))->display();
                });
        }
        return $transaction->discard();
    }

    public function render(): void
    {
        $this->setName(PiggyAuctions::getInstance()->getMessage("menus.auction-creator.title"));
        for ($i = 0; $i < $this->getInventory()->getSize(); $i++) $this->getInventory()->setItem($i, VanillaBlocks::INVISIBLE_BEDROCK()->asItem()->setCustomName(TextFormat::RESET));
        $this->getInventory()->setItem(13, $this->item);
        $this->getInventory()->setItem(29, VanillaBlocks::STAINED_CLAY()->setColor($this->item->getTypeId() === 0 ? DyeColor::RED() : DyeColor::GREEN())->asItem()->setCustomName(PiggyAuctions::getInstance()->getMessage("menus.auction-creator.create-auction", ["{STATUS}" => $this->item->getTypeId() === 0 ? TextFormat::RED : TextFormat::GREEN])));
        $this->getInventory()->setItem(31, VanillaItems::GOLD_INGOT()->setCustomName(PiggyAuctions::getInstance()->getMessage("menus.auction-creator.starting-bid", ["{MONEY}" => $this->startingBid])));
        $this->getInventory()->setItem(33, VanillaItems::CLOCK()->setCustomName(PiggyAuctions::getInstance()->getMessage("menus.auction-creator.duration", ["{DURATION}" => Utils::formatDuration($this->duration)])));
        $this->getInventory()->setItem(49, VanillaItems::ARROW()->setCustomName(PiggyAuctions::getInstance()->getMessage("menus.back")));
    }

    public function close(): void
    {
        $this->player->getInventory()->addItem($this->getInventory()->getItem(13));
        parent::close();
    }
}