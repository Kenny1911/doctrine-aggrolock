# DoctrineAggroLock

**DoctrineAggroLock** is **Doctrine ORM** extension that adds support optimistic lock of aggregate entities
(associations). **DoctrineAggroLock** prevents data loss during concurrent changes using an aggregate versioning
mechanism.

## Features

- Optimistic lock for aggregates and their associations
- Integration with Doctrine ORM
- Configuration via interfaces (`AggregateRoot` and `AggregateEntity`)
- Automatic aggregates versioning
- DDD (Domain-Driven Design) support

## Installation

```bash
composer require kenny1911/doctrine-aggrolock
```

Register the Doctrine Event Subscriber `Kenny1911\DoctrineAggroLock\AggroLock`.

Symfony Framework Example:

```yaml
services:
  
  Kenny1911\DoctrineAggroLock\AggregateEntitySubscriber:
    tags:
      - name: doctrine.event_subscriber
```

## Example

**Aggregate Root:**

```php
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\Mapping as ORM;
use Kenny1911\DoctrineAggroLock\AggregateRoot;
use Ramsey\Uuid\UuidInterface as Uuid;

/**
 * @final
 */
#[ORM\Entity]
class Order implements AggregateRoot
{
    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    /** @var Collection<array-key, OrderItem> */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,
    ) {
        $this->id = $id;
        $this->customerName = $customerName;
        $this->items = new ArrayCollection();
    }

    /**
     * @param non-negative-int $quantity
     * @param non-negative-int $price
     */
    public function addItem(Uuid $productId, int $quantity, int $price): void
    {
        $this->items->add(new OrderItem($this, $productId, $quantity, $price)); // TODO
    }

    /**
     * @return ReadableCollection<array-key, OrderItem>
     */
    public function getItems(): ReadableCollection
    {
        return $this->items;
    }
}
```

**Aggregate Entity**

```php
use Doctrine\ORM\Mapping as ORM;
use Kenny1911\DoctrineAggroLock\AggregateEntity;
use Kenny1911\DoctrineAggroLock\AggregateRoot;
use Ramsey\Uuid\UuidInterface as Uuid;

/**
 * @final
 */
#[ORM\Entity]
class OrderItem implements AggregateEntity
{
    /**
     * @param non-negative-int $quantity
     * @param non-negative-int $price
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
        private Order $order,
        
        #[ORM\Column(type: 'uuid')]
        private Uuid $productId,
        
        #[ORM\Column(type: 'integer')]
        private int $quantity,
        
        #[ORM\Column(type: 'integer')]
        private float $price
    ) {}

    public function getAggregateRoot(): AggregateRoot
    {
        return $this->order;
    }
}
```

### Explanations

1. **Aggregate Root** (`Order`) implements the `AggregateRoot` interface. It guarantees that the object is the root of
the aggregate and not just an entity.

2. **Aggregate Entities** (`OrderItem`) implement the `AggregateEntity` interface and are associated with the aggregate
root through a `ManyToOne` relation.

3. **Optimistic Locking** is implemented using the standard **Doctrine ORM** approach with a special version field
(`version`).
