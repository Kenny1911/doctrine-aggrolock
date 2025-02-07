# DoctrineAggroLock

**DoctrineAggroLock** — это расширение для **Doctrine ORM**, добавляющее поддержку оптимистичной блокировки для сущостей
агрегата (ассоциаций). **DoctrineAggroLock** предотвращает потерю данных при конкурентных изменениях, используя механизм
версионирования агрегатов.

## Возможности

- Оптимистичная блокировка для агрегатов и их ассоциаций
- Прозрачная интеграция с Doctrine ORM
- Настройка через интерфейсы (`AggregateRoot` и `AggregateEntity`)
- Автоматическое версионирование агрегата
- Поддержка DDD (Domain-Driven Design)

## Установка

```bash
composer require kenny1911/doctrine-aggrolock
```

Зарегистрировать Doctrine Event Subscriber `Kenny1911\DoctrineAggroLock\AggroLock`.

Пример на Symfony:

```yaml
services:
  
  Kenny1911\DoctrineAggroLock\AggregateEntitySubscriber:
    tags:
      - name: doctrine.event_subscriber
```

## Пример использования

**Корень Агрегата:**

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

**Сущность Агрегата**

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

### Пояснения

1. **Корень агрегата** (`Order`) реализует интерфейс `AggregateRoot`. Это гарантирует, что объект является корнем
агрегата, а не просто сущностью.

2. **Сущности агрегата** (`OrderItem`) реализуют интерфейс `AggregateEntity` и ассоциируются с корнем агрегата через
связь `ManyToOne`.

3. **Оптимистичная блокировка** осуществляется стандартным для **Doctrine ORM** способом - через специальное поле версии
(`version`).
