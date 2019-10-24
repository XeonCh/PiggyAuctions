-- #! sqlite
-- #{ piggyauctions

-- # { init
CREATE TABLE IF NOT EXISTS auctions
(
    id           INTEGER PRIMARY KEY,
    auctioneer   VARCHAR(15),
    item         TEXT,
    startdate    INTEGER,
    enddate      INTEGER,
    claimed      INTEGER,
    claimed_bids TEXT,
    bids         TEXT
);
-- # }

-- # { load
SELECT *
FROM auctions;
-- # }

-- # { add
-- #    :auctioneer string
-- #    :item string
-- #    :startdate int
-- #    :enddate int
-- #    :claimed int
-- #    :claimed_bids string
-- #    :bids string
INSERT INTO auctions (auctioneer, item, startdate, enddate, claimed, claimed_bids, bids)
VALUES (:auctioneer, :item, :startdate, :enddate, :claimed, :claimed_bids, :bids);
-- # }

-- # { update
-- #    :id int
-- #    :claimed int
-- #    :claimed_bids string
-- #    :bids string
UPDATE auctions
SET claimed      = :claimed,
    claimed_bids = :claimed_bids,
    bids         = :bids
WHERE id = :id;
-- # }

-- # { remove
-- #    :id int
DELETE
FROM auctions
WHERE id = :id;
-- # }

-- #}