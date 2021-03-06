<?php

namespace HireVoice\Neo4j\Tests;
use HireVoice\Neo4j;

class EntityManagerTest extends \PHPUnit_Framework_TestCase
{
    private function getEntityManager()
    {
        $client = new \Everyman\Neo4j\Client(new \Everyman\Neo4j\Transport('localhost', 7474));
        return new Neo4j\EntityManager($client, new Neo4j\MetaRepository);
    }

    function testStoreSimpleEntity()
    {
        $entity = new Entity\Movie;
        $entity->setTitle('Return of the king');

        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();

        $em = $this->getEntityManager();
        $movie = $em->find(get_class($entity), $entity->getId());

        $this->assertEquals('Return of the king', $movie->getTitle());
    }

    function testStoreRelations()
    {
        $aragorn = new Entity\Person;
        $aragorn->setFirstName('Viggo');
        $aragorn->setLastName('Mortensen');

        $legolas = new Entity\Person;
        $legolas->setFirstName('Orlando');
        $legolas->setLastName('Bloom');

        $entity = new Entity\Movie;
        $entity->setTitle('Return of the king');
        $entity->addActor($aragorn);
        $entity->addActor($legolas);

        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();

        $em = $this->getEntityManager();
        $movie = $em->find(get_class($entity), $entity->getId());

        $actors = array();
        foreach ($movie->getActors() as $actor) {
            $actors[] = $actor->getFirstName();
        }

        $this->assertEquals(array('Viggo', 'Orlando'), $actors);
    }

    function testLookupIndex()
    {
        $entity = new Entity\Movie;
        $entity->setTitle('Return of the king');

        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();

        $movieKey = $entity->getMovieRegistryCode();
        
        $em = $this->getEntityManager();
        $repository = $em->getRepository(get_class($entity));
        $movie = $repository->findOneByMovieRegistryCode($movieKey);

        $this->assertEquals('Return of the king', $movie->getTitle());

        $movies = $repository->findByMovieRegistryCode($movieKey);
        $this->assertCount(1, $movies);
        $this->assertEquals($entity, $movies->first()->getEntity());
    }

    /**
     * @expectedException HireVoice\Neo4j\Exception
     */
    function testSearchMissingProperty()
    {
        $em = $this->getEntityManager();
        $repository = $em->getRepository('HireVoice\\Neo4j\\Tests\\Entity\\Movie');

        $repository->findByMovieRegistrationCode('Return of the king');
    }

    /**
     * @expectedException HireVoice\Neo4j\Exception
     */
    function testSearchUnindexedProperty()
    {
        $em = $this->getEntityManager();
        $repository = $em->getRepository('HireVoice\\Neo4j\\Tests\\Entity\\Movie');

        $repository->findByTitle('Return of the king');
    }

    function testRelationsDoNotDuplicate()
    {
        $aragorn = new Entity\Person;
        $aragorn->setFirstName('Viggo');
        $aragorn->setLastName('Mortensen');

        $legolas = new Entity\Person;
        $legolas->setFirstName('Orlando');
        $legolas->setLastName('Bloom');

        $entity = new Entity\Movie;
        $entity->setTitle('Return of the king');
        $entity->addActor($aragorn);
        $entity->addActor($legolas);

        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();

        $em->persist($entity);
        $em->flush();

        $em = $this->getEntityManager();
        $movie = $em->find(get_class($entity), $entity->getId());

        $this->assertCount(2, $movie->getActors());
    }

    function testManyToOneRelation()
    {
        $legolas = new Entity\Person;
        $legolas->setFirstName('Orlando');
        $legolas->setLastName('Bloom');

        $entity = new Entity\Movie;
        $entity->setTitle('Return of the king');
        $entity->setMainActor($legolas);

        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();

        $em = $this->getEntityManager();
        $movie = $em->find(get_class($entity), $entity->getId());

        $this->assertEquals('Orlando', $movie->getMainActor()->getFirstName());
    }

    /**
     * @expectedException HireVoice\Neo4j\Exception
     */
    function testPersistNonEntity()
    {
        $em = $this->getEntityManager();
        $em->persist($this);
    }

    /**
     * @expectedException HireVoice\Neo4j\Exception
     */
    function testPersistEntityWithoutPersistableId()
    {
        $em = $this->getEntityManager();
        $em->persist(new FailedEntity);
    }

    function testReadOnlyProperty()
    {
        $movie = new Entity\Movie;
        $movie->setTitle('Return of the king');

        $cinema = new Entity\Cinema;
        $cinema->setName('Paramount');
        $cinema->addPresentedMovie($movie);

        $cinema2 = new Entity\Cinema;
        $cinema2->setName('Fake');
        $movie->addCinema($cinema2);

        $em = $this->getEntityManager();
        $em->persist($cinema);
        $em->flush();

        $em = $this->getEntityManager();
        $movie = $em->find(get_class($movie), $movie->getId());
        $this->assertCount(1, $movie->getCinemas());
        $this->assertEquals('Paramount', $movie->getCinemas()->first()->getName());
    }

    function testWriteOnlyProperty()
    {
        $movie = new Entity\Movie;
        $movie->setTitle('Return of the king');

        $cinema = new Entity\Cinema;
        $cinema->setName('Paramount');
        $cinema->getRejectedMovies()->add($movie);

        $em = $this->getEntityManager();
        $em->persist($cinema);
        $em->flush();

        $em = $this->getEntityManager();
        $cinema = $em->find(get_class($cinema), $cinema->getId());
        $this->assertCount(0, $cinema->getRejectedMovies());
    }

    function testStoreDate()
    {
        $date = new \DateTime('-4 month');

        $movie = new Entity\Movie;
        $movie->setReleaseDate($date);

        $em = $this->getEntityManager();
        $em->persist($movie);
        $em->flush();

        $em = $this->getEntityManager();
        $movie = $em->find(get_class($movie), $movie->getId());

        $this->assertEquals($date, $movie->getReleaseDate());
    }

    function testAutostoreDates()
    {
        $date = new \DateTime;

        $aragorn = new Entity\Person;
        $aragorn->setFirstName('Viggo');
        $aragorn->setLastName('Mortensen');

        $entity = new Entity\Movie;
        $entity->setTitle('Return of the king');
        $entity->addActor($aragorn);

        $em = $this->getEntityManager();
        $em->setDateGenerator(function () {
            return 'foobar';
        });
        $em->persist($entity);
        $em->flush();

        $result = $em->createGremlinQuery('g.v(:movie).map')
            ->set('movie', $entity)
            ->getMap();

        $this->assertEquals('foobar', $result['creationDate']);
        $this->assertEquals('foobar', $result['updateDate']);

        $result = $em->createGremlinQuery('g.v(:movie).outE.map')
            ->set('movie', $entity)
            ->getMap();

        $this->assertEquals('foobar', $result['creationDate']);

        $em->setDateGenerator(function () {
            return 'baz';
        });

        $em->persist($entity);
        $em->flush();

        $result = $em->createGremlinQuery('g.v(:movie).map')
            ->set('movie', $entity)
            ->getMap();

        $this->assertEquals('foobar', $result['creationDate']);
        $this->assertEquals('baz', $result['updateDate']);
    }
}

/**
 * @Neo4j\Annotation\Entity
 */
class FailedEntity
{
    /**
     * @Neo4j\Annotation\Property
     */
    private $name;
}

