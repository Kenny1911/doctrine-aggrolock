<?php

declare(strict_types=1);

namespace Kenny1911\DoctrineAggroLock;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\UnitOfWork;

final class AggroLock implements EventSubscriber
{
    public function getSubscribedEvents(): array
    {
        return [
            Events::onFlush,
        ];
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $em = $event->getObjectManager();

        foreach ($this->collectAggregateRoots($em) as $aggregateRoot) {
            $this->updateAggregateRoot($aggregateRoot, $em);
        }
    }

    /**
     * @return list<AggregateRoot>
     */
    private function collectAggregateRoots(EntityManagerInterface $em): array
    {
        $uow = $em->getUnitOfWork();
        $aggregateRoots = [];

        foreach ([...$uow->getScheduledEntityInsertions(), ...$uow->getScheduledEntityUpdates(), ...$uow->getScheduledEntityDeletions()] as $entity) {
            if (!$entity instanceof AggregateEntity) {
                continue;
            }

            $aggregateRoot = $entity->getAggregateRoot();

            if (false === $em->getClassMetadata($aggregateRoot::class)->isVersioned) {
                continue;
            }

            if (UnitOfWork::STATE_MANAGED !== $em->getUnitOfWork()->getEntityState($aggregateRoot)) {
                continue;
            }

            $aggregateRoots[] = $aggregateRoot;
        }

        return $aggregateRoots;
    }

    private function updateAggregateRoot(AggregateRoot $aggregateRoot, EntityManagerInterface $em): void
    {
        $oid = spl_object_id($aggregateRoot);
        $uow = $em->getUnitOfWork();
        $metadata = $em->getClassMetadata($aggregateRoot::class);

        foreach ($metadata->getIdentifierFieldNames() as $fieldName) {
            $uow->setOriginalEntityProperty($oid, $fieldName, null);
            $uow->recomputeSingleEntityChangeSet($metadata, $aggregateRoot);
        }
    }
}
