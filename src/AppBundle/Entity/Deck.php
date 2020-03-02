<?php

namespace AppBundle\Entity;

class Deck extends \AppBundle\Model\ExportableDeck implements \JsonSerializable {
    /**
     * @return array
     */
    public function getHistory() {
        $slots = $this->getSlots();
        $cards = $slots->getContent();
        $sideslots = $this->getSideslots();
        $sidecards = $sideslots->getContent();

        $snapshots = [];
        $changes = $this->getChanges();
        $savedChanges = [];
        $unsavedChanges = [];

        foreach ($changes as $change) {
            if ($change->getIsSaved()) {
                array_push($savedChanges, $change);
            } else {
                array_unshift($unsavedChanges, $change);
            }
        }
        $array['unsaved'] = count($unsavedChanges);

        // recreating the versions with the variation info, starting from $preversion
        $preversion = $cards;
        $sidepreversion = $sidecards;
        foreach ($savedChanges as $change) {
            $variation = json_decode($change->getVariation(), true);
            $row = [
                'variation' => $variation,
                'is_saved' => $change->getIsSaved(),
                'version' => $change->getVersion(),
                'content' => [
                    'main' => $preversion,
                    'side' => $sidepreversion
                ],
                'date_creation' => $change->getDateCreation()->format('c'),
            ];
            array_unshift($snapshots, $row);

            // applying variation to create 'next' (older) preversion
            foreach ($variation[0] as $code => $qty) {
                if (isset($preversion[$code])) {
                    $preversion[$code] = $preversion[$code] - $qty;
                    if ($preversion[$code] == 0) {
                        unset ($preversion[$code]);
                    }
                }
            }

            foreach ($variation[1] as $code => $qty) {
                if (!isset($preversion[$code])) {
                    $preversion[$code] = 0;
                }
                $preversion[$code] = $preversion[$code] + $qty;
            }

            if (!isset($variation[2])) {
                $variation[2] = [];
            }

            foreach ($variation[2] as $code => $qty) {
                if (isset($sidepreversion[$code])) {
                    $sidepreversion[$code] = $sidepreversion[$code] - $qty;
                    if ($sidepreversion[$code] == 0) {
                        unset ($sidepreversion[$code]);
                    }
                }
            }

            if (!isset($variation[3])) {
                $variation[3] = [];
            }

            foreach ($variation[3] as $code => $qty) {
                if (!isset($sidepreversion[$code])) {
                    $sidepreversion[$code] = 0;
                }
                $sidepreversion[$code] = $sidepreversion[$code] + $qty;
            }

            ksort($preversion);
            ksort($sidepreversion);
        }

        // add last know version with empty diff
        $row = [
            'variation' => null,
            'is_saved' => true,
            'version' => "0.0",
            'content' => [
                'main' => $preversion,
                'side' => $sidepreversion,
            ],
            'date_creation' => $this->getDateCreation()->format('c')
        ];
        array_unshift($snapshots, $row);

        // recreating the snapshots with the variation info, starting from $postversion
        $postversion = $cards;
        $sidepostversion = $sidecards;
        foreach ($unsavedChanges as $change) {
            $variation = json_decode($change->getVariation(), true);
            $row = [
                'variation' => $variation,
                'is_saved' => $change->getIsSaved(),
                'version' => $change->getVersion(),
                'date_creation' => $change->getDateCreation()->format('c'),
            ];

            // applying variation to postversion
            foreach ($variation[0] as $code => $qty) {
                if (!isset ($postversion[$code])) {
                    $postversion[$code] = 0;
                }
                $postversion[$code] = $postversion[$code] + $qty;
            }

            foreach ($variation[1] as $code => $qty) {
                $postversion[$code] = $postversion[$code] - $qty;
                if ($postversion[$code] == 0) {
                    unset ($postversion[$code]);
                }
            }

            if (!isset($variation[2])) {
                $variation[2] = [];
            }

            foreach ($variation[2] as $code => $qty) {
                if (!isset ($sidepostversion[$code])) {
                    $sidepostversion[$code] = 0;
                }
                $sidepostversion[$code] = $sidepostversion[$code] + $qty;
            }

            if (!isset($variation[3])) {
                $variation[3] = [];
            }

            foreach ($variation[3] as $code => $qty) {
                $sidepostversion[$code] = $sidepostversion[$code] - $qty;
                if ($sidepostversion[$code] == 0) {
                    unset ($sidepostversion[$code]);
                }
            }

            ksort($postversion);
            ksort($sidepostversion);

            // add postversion with variation that lead to it
            $row['content'] = [
                'main' => $postversion,
                'side' => $sidepostversion
            ];
            array_push($snapshots, $row);
        }

        return $snapshots;
    }

    public function jsonSerialize() {
        $array = parent::getArrayExport();
        $array['is_published'] = false;
        $array['problem'] = $this->getProblem();
        $array['tags'] = $this->getTags();

        return $array;
    }

    public function getIsUnsaved() {
        $changes = $this->getChanges();

        foreach ($changes as $change) {
            if (!$change->getIsSaved()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @var integer
     */
    private $id;
    /**
     * @var string
     */
    private $name;
    /**
     * @var \DateTime
     */
    private $dateCreation;
    /**
     * @var \DateTime
     */
    private $dateUpdate;
    /**
     * @var string
     */
    private $descriptionMd;
    /**
     * @var string
     */
    private $problem;
    /**
     * @var string
     */
    private $tags;
    /**
     * @var integer
     */
    private $majorVersion;
    /**
     * @var integer
     */
    private $minorVersion;
    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $slots;
    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $sideslots;
    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $children;
    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $changes;
    /**
     * @var \AppBundle\Entity\User
     */
    private $user;
    /**
     * @var \AppBundle\Entity\Pack
     */
    private $lastPack;
    /**
     * @var \AppBundle\Entity\Decklist
     */
    private $parent;

    /**
     * Constructor
     */
    public function __construct() {
        $this->slots = new \Doctrine\Common\Collections\ArrayCollection();
        $this->sideslots = new \Doctrine\Common\Collections\ArrayCollection();
        $this->children = new \Doctrine\Common\Collections\ArrayCollection();
        $this->changes = new \Doctrine\Common\Collections\ArrayCollection();
        $this->fellowships = new \Doctrine\Common\Collections\ArrayCollection();
        $this->minorVersion = 0;
        $this->majorVersion = 0;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Deck
     */
    public function setName($name) {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Set dateCreation
     *
     * @param \DateTime $dateCreation
     *
     * @return Deck
     */
    public function setDateCreation($dateCreation) {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    /**
     * Get dateCreation
     *
     * @return \DateTime
     */
    public function getDateCreation() {
        return $this->dateCreation;
    }

    /**
     * Set dateUpdate
     *
     * @param \DateTime $dateUpdate
     *
     * @return Deck
     */
    public function setDateUpdate($dateUpdate) {
        $this->dateUpdate = $dateUpdate;

        return $this;
    }

    /**
     * Get dateUpdate
     *
     * @return \DateTime
     */
    public function getDateUpdate() {
        return $this->dateUpdate;
    }

    /**
     * Set descriptionMd
     *
     * @param string $descriptionMd
     *
     * @return Deck
     */
    public function setDescriptionMd($descriptionMd) {
        $this->descriptionMd = $descriptionMd;

        return $this;
    }

    /**
     * Get descriptionMd
     *
     * @return string
     */
    public function getDescriptionMd() {
        return $this->descriptionMd;
    }

    /**
     * Set problem
     *
     * @param string $problem
     *
     * @return Deck
     */
    public function setProblem($problem) {
        $this->problem = $problem;

        return $this;
    }

    /**
     * Get problem
     *
     * @return string
     */
    public function getProblem() {
        return $this->problem;
    }

    /**
     * Set tags
     *
     * @param string $tags
     *
     * @return Deck
     */
    public function setTags($tags) {
        $this->tags = $tags;

        return $this;
    }

    /**
     * Get tags
     *
     * @return string
     */
    public function getTags() {
        return $this->tags;
    }

    /**
     * Add slot
     *
     * @param \AppBundle\Entity\Deckslot $slot
     *
     * @return Deck
     */
    public function addSlot(\AppBundle\Entity\Deckslot $slot) {
        $this->slots[] = $slot;

        return $this;
    }

    /**
     * Remove slot
     *
     * @param \AppBundle\Entity\Deckslot $slot
     */
    public function removeSlot(\AppBundle\Entity\Deckslot $slot) {
        $this->slots->removeElement($slot);
    }

    /**
     * Get slots
     *
     * @return \AppBundle\Model\SlotCollectionInterface
     */
    public function getSlots() {
        return new \AppBundle\Model\SlotCollectionDecorator($this->slots);
    }

    /**
     * Add sideslot
     *
     * @param \AppBundle\Entity\Decksideslot $sideslot
     *
     * @return Deck
     */
    public function addSideslot(\AppBundle\Entity\Decksideslot $sideslot) {
        $this->sideslots[] = $sideslot;

        return $this;
    }

    /**
     * Remove sideslot
     *
     * @param \AppBundle\Entity\Decksideslot $sideslot
     */
    public function removeSideslot(\AppBundle\Entity\Decksideslot $sideslot) {
        $this->sideslots->removeElement($sideslot);
    }

    /**
     * Get sideslots
     *
     * @return \AppBundle\Model\SlotCollectionInterface
     */
    public function getSideslots() {
        return new \AppBundle\Model\SlotCollectionDecorator($this->sideslots);
    }

    /**
     * Add child
     *
     * @param \AppBundle\Entity\Decklist $child
     *
     * @return Deck
     */
    public function addChild(\AppBundle\Entity\Decklist $child) {
        $this->children[] = $child;

        return $this;
    }

    /**
     * Remove child
     *
     * @param \AppBundle\Entity\Decklist $child
     */
    public function removeChild(\AppBundle\Entity\Decklist $child) {
        $this->children->removeElement($child);
    }

    /**
     * Get children
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getChildren() {
        return $this->children;
    }

    /**
     * Add change
     *
     * @param \AppBundle\Entity\Deckchange $change
     *
     * @return Deck
     */
    public function addChange(\AppBundle\Entity\Deckchange $change) {
        $this->changes[] = $change;

        return $this;
    }

    /**
     * Remove change
     *
     * @param \AppBundle\Entity\Deckchange $change
     */
    public function removeChange(\AppBundle\Entity\Deckchange $change) {
        $this->changes->removeElement($change);
    }

    /**
     * Get changes
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getChanges() {
        return $this->changes;
    }

    /**
     * Set user
     *
     * @param \AppBundle\Entity\User $user
     *
     * @return Deck
     */
    public function setUser(\AppBundle\Entity\User $user = null) {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \AppBundle\Entity\User
     */
    public function getUser() {
        return $this->user;
    }

    /**
     * Set lastPack
     *
     * @param \AppBundle\Entity\Pack $lastPack
     *
     * @return Deck
     */
    public function setLastPack(Pack $lastPack = null) {
        $this->lastPack = $lastPack;

        return $this;
    }

    /**
     * Get lastPack
     *
     * @return \AppBundle\Entity\Pack
     */
    public function getLastPack() {
        return $this->lastPack;
    }

    /**
     * Set parent
     *
     * @param \AppBundle\Entity\Decklist $parent
     *
     * @return Deck
     */
    public function setParent(\AppBundle\Entity\Decklist $parent = null) {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent
     *
     * @return \AppBundle\Entity\Decklist
     */
    public function getParent() {
        return $this->parent;
    }

    /**
     * Set majorVersion
     *
     * @param integer $majorVersion
     *
     * @return Deck
     */
    public function setMajorVersion($majorVersion) {
        $this->majorVersion = $majorVersion;

        return $this;
    }

    /**
     * Get majorVersion
     *
     * @return integer
     */
    public function getMajorVersion() {
        return $this->majorVersion;
    }

    /**
     * Set minorVersion
     *
     * @param integer $minorVersion
     *
     * @return Deck
     */
    public function setMinorVersion($minorVersion) {
        $this->minorVersion = $minorVersion;

        return $this;
    }

    /**
     * Get minorVersion
     *
     * @return integer
     */
    public function getMinorVersion() {
        return $this->minorVersion;
    }

    public function getVersion() {
        return $this->majorVersion . "." . $this->minorVersion;
    }

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $fellowships;
    private $allFellowships;

    /**
     * Add fellowship
     *
     * @param \AppBundle\Entity\FellowshipDeck $fellowship
     *
     * @return Deck
     */
    public function addFellowship(\AppBundle\Entity\FellowshipDeck $fellowship) {
        $this->fellowships[] = $fellowship;

        return $this;
    }

    /**
     * Remove fellowship
     *
     * @param \AppBundle\Entity\FellowshipDeck $fellowship
     */
    public function removeFellowship(\AppBundle\Entity\FellowshipDeck $fellowship) {
        $this->fellowships->removeElement($fellowship);
    }

    /**
     * Get fellowships
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getFellowships() {
        return $this->fellowships;
    }

    /**
     * Get allFellowships
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getAllFellowships() {
        $childrenFellowships = $this->getFellowships()->toArray();

        foreach ($this->getChildren() as &$child) {
            $childrenFellowships = array_merge($childrenFellowships, $child->getFellowships()->toArray());
        }

        return $childrenFellowships;
    }

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $questlogs;

    /**
     * Add questlog
     *
     * @param \AppBundle\Entity\QuestlogDeck $questlog
     *
     * @return Deck
     */
    public function addQuestlog(\AppBundle\Entity\QuestlogDeck $questlog) {
        $this->questlogs[] = $questlog;

        return $this;
    }

    /**
     * Remove questlog
     *
     * @param \AppBundle\Entity\QuestlogDeck $questlog
     */
    public function removeQuestlog(\AppBundle\Entity\QuestlogDeck $questlog) {
        $this->questlogs->removeElement($questlog);
    }

    /**
     * Get questlogs
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getQuestlogs() {
        return $this->questlogs;
    }

    /**
     * Get allQuestlogs
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getAllQuestlogs() {
        $allQuestlogs = $this->getQuestlogs()->toArray();
        return $allQuestlogs;
    /*
        return array_filter($allQuestlogs, function($k) {
            return $k->getQuestlog()->getIsPublic();
        });
    */
    }
}