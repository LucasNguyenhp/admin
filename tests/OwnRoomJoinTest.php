<?php

namespace App\Tests;

use App\Repository\LobbyWaitungUserRepository;
use App\Repository\RoomsRepository;
use App\Repository\ServerRepository;
use App\Repository\UserRepository;
use App\Service\JoinUrlGeneratorService;
use App\Service\RoomService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OwnRoomJoinTest extends WebTestCase
{
    public function test_hasStart_toearly_noModerator_no_Lobby(): void
    {
        $client = static::createClient();
        $room = $this->getRoomByName('Room with Start and no Participants list');
        $crawler = $client->request('GET', '/myRoom/start/' . $room->getUid());
        $urlGen = $this->getContainer()->get(UrlGeneratorInterface::class);

        $url = $urlGen->generate('join_index_no_slug', array('snack' => 'Der Beitritt ist nur von ' . $room->getStart()->modify('-30min')->format('d.m.Y H:i T') . ' bis ' . $room->getEnddate()->format('d.m.Y H:i T') . ' möglich'));
        self::assertTrue($client->getResponse()->isRedirect());

    }

    public function test_hasStart_waitingTime_noModerator_no_Lobby(): void
    {
        $client = static::createClient();
        $room = $this->getRoomByName('Room with Start and no Participants list');
        $room->setStart((new \DateTime())->modify('+15min'));
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($room);
        $em->flush();

        $crawler = $client->request('GET', '/myRoom/start/' . $room->getUid());

        $urlGen = $this->getContainer()->get(UrlGeneratorInterface::class);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', $room->getName());
        $this->assertStringNotContainsString('https://privacy.dev', $client->getResponse()->getContent());
        $this->assertStringContainsString('https://test.img', $client->getResponse()->getContent());
    }

    public function test_hasStart_waitingTime_noModerator_no_Lobby_noServerLicense(): void
    {
        $client = static::createClient();
        $room = $this->getRoomByName('Room with Start and no Participants list');
        $room->setStart((new \DateTime())->modify('+15min'));
        $server = $room->getServer();
        $server->setLicenseKey(null);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($server);
        $em->persist($room);
        $em->flush();

        $crawler = $client->request('GET', '/myRoom/start/' . $room->getUid());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', $room->getName());
        $this->assertStringNotContainsString('https://privacy.dev', $client->getResponse()->getContent());
        $this->assertStringNotContainsString('https://test.img', $client->getResponse()->getContent());

    }


    public function test_hasStart_waitingTime_noModerator_no_Lobby_toWaitingSite(): void
    {
        $client = static::createClient();
        $room = $this->getRoomByName('Room with Start and no Participants list');
        $room->setStart((new \DateTime())->modify('+15min'));
        $server = $room->getServer();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($room);
        $em->flush();
        $crawler = $client->request('GET', '/myRoom/start/' . $room->getUid());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', $room->getName());
        $buttonCrawlerNode = $crawler->selectButton('Im Browser beitreten');
        $form = $buttonCrawlerNode->form();
        $form['join_my_room[name]'] = 'Test User 123';
        $client->submit($form);
        $urlGenerator = self::getContainer()->get(UrlGeneratorInterface::class);
        $this->assertTrue($client->getResponse()->isRedirect($urlGenerator->generate('room_waiting', array('name' => 'Test User 123', 'uid' => $room->getUid(), 'type' => 'b'))));
        $buttonCrawlerNode = $crawler->selectButton('Mit der Elektron-App beitreten');
        $form = $buttonCrawlerNode->form();
        $form['join_my_room[name]'] = 'Test User 123';
        $client->submit($form);
        $urlGenerator = self::getContainer()->get(UrlGeneratorInterface::class);
        $this->assertTrue($client->getResponse()->isRedirect($urlGenerator->generate('room_waiting', array('name' => 'Test User 123', 'uid' => $room->getUid(), 'type' => 'a'))));
    }
    public function test_hasStart_waitingTime_isModerator_no_Lobby(): void
    {
        $client = static::createClient();
        $userRepo = $this->getContainer()->get(UserRepository::class);
        $user = $userRepo->findOneBy(array('email' => 'test@local.de'));
        $client->loginUser($user);
        $room = $this->getRoomByName('Room with Start and no Participants list');
        $room->setStart((new \DateTime())->modify('+15min'));
        $server = $room->getServer();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($room);
        $em->flush();
        $crawler = $client->request('GET', '/myRoom/start/' . $room->getUid());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', $room->getName());
        $buttonCrawlerNode = $crawler->selectButton('Mit der Elektron-App beitreten');
        $form = $buttonCrawlerNode->form();
        $form['join_my_room[name]'] = 'Test User 123';
        $client->submit($form);
        $urlGenerator = self::getContainer()->get(UrlGeneratorInterface::class);
        $urlGenService = self::getContainer()->get(RoomService::class);
        $this->assertTrue($client->getResponse()->isRedirect($urlGenService->joinUrl('a',$room,'Test User 123',true)));


        $buttonCrawlerNode = $crawler->selectButton('Im Browser beitreten');
        $form = $buttonCrawlerNode->form();
        $form['join_my_room[name]'] = 'Test User 123';
        $client->submit($form);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString("jwt: '".$urlGenService->generateJwt($room,$user,'Test User 123'),$client->getResponse()->getContent());
        self::assertStringContainsString( "displayName: 'Test User 123'",$client->getResponse()->getContent());
        self::assertStringContainsString( " roomName: '".$room->getUid()."'",$client->getResponse()->getContent());

    }

    public function testmyWaiting(): void
    {
        $client = static::createClient();
        $urlGenerator = self::getContainer()->get(RoomService::class);
        $url = self::getContainer()->get(UrlGeneratorInterface::class);
        $room = $this->getRoomByName('Room with Start and no Participants list');
        $manager = $this->getContainer()->get(EntityManagerInterface::class);
        $room->setStart((new \DateTime())->modify('+10min'));
        $manager->persist($room);
        $manager->flush();
        $crawler = $client->request('GET', '/mywaiting/check/' . $room->getUid() . '/Test User 123/b');
        $this->assertResponseIsSuccessful();
        $this->assertEquals('{"error":true}', $client->getResponse()->getContent());
        $room->setStart((new \DateTime())->modify('-10min'));
        $manager->persist($room);
        $manager->flush();
        $urlGenService = self::getContainer()->get(RoomService::class);
        $crawler = $client->request('GET', $url->generate('room_waiting', array('uid' => $room->getUid(), 'name' => 'Test User 123', 'type' => 'b')));
        self::assertStringContainsString("jwt: '".$urlGenService->generateJwt($room,null,'Test User 123'),$client->getResponse()->getContent());
        self::assertStringContainsString( "displayName: 'Test User 123'",$client->getResponse()->getContent());
        self::assertStringContainsString( " roomName: '".$room->getUid()."'",$client->getResponse()->getContent());
        $crawler = $client->request('GET', $url->generate('room_waiting', array('uid' => $room->getUid(), 'name' => 'Test User 123', 'type' => 'a')));
        $this->assertTrue($client->getResponse()->isRedirect($urlGenService->joinUrl('a',$room,'Test User 123',false)));
    }

    public function test_hasStart_noModerator_hasLobby(): void
    {
        $client = static::createClient();
        $room = $this->getRoomByName('Room with Start and no Participants list and Lobby Activated');
        $room->setStart((new \DateTime())->modify('+15min'));
        $server = $room->getServer();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($room);
        $em->flush();
        $crawler = $client->request('GET', '/myRoom/start/' . $room->getUid());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', $room->getName());

        $buttonCrawlerNode = $crawler->selectButton('Mit der Elektron-App beitreten');
        $form = $buttonCrawlerNode->form();
        $form['join_my_room[name]'] = 'Test User 123';
        $client->submit($form);
        $urlGenService = self::getContainer()->get(RoomService::class);
        self::assertResponseIsSuccessful();
        $lobbyRepo = self::getContainer()->get(LobbyWaitungUserRepository::class);
        $lobbyUSer = $lobbyRepo->findBy(array('showName'=>'Test User 123'));
        self::assertIsInt(sizeof($lobbyUSer),1);

        self::assertStringContainsString("var type = 'a';",$client->getResponse()->getContent());
        self::assertStringContainsString(" <script src='https://meet.jit.si2/external_api.js'></script>",$client->getResponse()->getContent());
        self::assertStringNotContainsString("jwt",$client->getResponse()->getContent());

        $buttonCrawlerNode = $crawler->selectButton('Im Browser beitreten');
        $form = $buttonCrawlerNode->form();
        $form['join_my_room[name]'] = 'Test User 123';
        $client->submit($form);
        $lobbyUSer = $lobbyRepo->findBy(array('showName'=>'Test User 123'));
        self::assertIsInt(sizeof($lobbyUSer),2);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString("var type = 'b';",$client->getResponse()->getContent());
        self::assertStringContainsString(" <script src='https://meet.jit.si2/external_api.js'></script>",$client->getResponse()->getContent());
        self::assertStringNotContainsString("jwt",$client->getResponse()->getContent());

    }

    public function test_hasStart_isModerator_hasLobby(): void
    {
        $client = static::createClient();
        $userRepo = $this->getContainer()->get(UserRepository::class);
        $user = $userRepo->findOneBy(array('email' => 'test@local.de'));
        $client->loginUser($user);
        $room = $this->getRoomByName('Room with Start and no Participants list and Lobby Activated');
        $room->setStart((new \DateTime())->modify('+15min'));
        $server = $room->getServer();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($room);
        $em->flush();
        $crawler = $client->request('GET', '/myRoom/start/' . $room->getUid());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', $room->getName());

        $buttonCrawlerNode = $crawler->selectButton('Mit der Elektron-App beitreten');
        $form = $buttonCrawlerNode->form();
        $form['join_my_room[name]'] = 'Test User 123';
        $client->submit($form);
        $urlGenService = self::getContainer()->get(RoomService::class);
        self::assertResponseIsSuccessful();
        $lobbyRepo = self::getContainer()->get(LobbyWaitungUserRepository::class);
        $lobbyUSer = $lobbyRepo->findBy(array('showName'=>'Test User 123'));
        self::assertIsInt(sizeof($lobbyUSer),1);
        self::assertStringContainsString("jwt: '".$urlGenService->generateJwt($room,$user,'Test User 123'),$client->getResponse()->getContent());
        self::assertStringContainsString( "displayName: 'Test User 123'",$client->getResponse()->getContent());
        self::assertStringContainsString( " roomName: '".$room->getUid()."'",$client->getResponse()->getContent());
        self::assertStringContainsString( 'room/lobby/start/moderator/a/'.$room->getUidReal(),$client->getResponse()->getContent());

        $buttonCrawlerNode = $crawler->selectButton('Im Browser beitreten');
        $form = $buttonCrawlerNode->form();
        $form['join_my_room[name]'] = 'Test User 123';
        $client->submit($form);
        $lobbyUSer = $lobbyRepo->findBy(array('showName'=>'Test User 123'));
        self::assertIsInt(sizeof($lobbyUSer),2);
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString( 'room/lobby/start/moderator/a/'.$room->getUidReal(),$client->getResponse()->getContent());
        self::assertStringContainsString("jwt: '".$urlGenService->generateJwt($room,$user,'Test User 123'),$client->getResponse()->getContent());
        self::assertStringContainsString( "displayName: 'Test User 123'",$client->getResponse()->getContent());
        self::assertStringContainsString( " roomName: '".$room->getUid()."'",$client->getResponse()->getContent());
    }

    public function test_hasNoStart_isModerator_NoLobby(): void
    {
        $client = static::createClient();
        $userRepo = $this->getContainer()->get(UserRepository::class);
        $user = $userRepo->findOneBy(array('email' => 'test@local.de'));
        $client->loginUser($user);
        $room = $this->getRoomByName('This Room has no participants and fixed room');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($room);
        $em->flush();
        $crawler = $client->request('GET', '/myRoom/start/' . $room->getUid());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', $room->getName());

        $buttonCrawlerNode = $crawler->selectButton('Mit der Elektron-App beitreten');
        $form = $buttonCrawlerNode->form();
        $form['join_my_room[name]'] = 'Test User 123';
        $client->submit($form);
        $urlGenerator = self::getContainer()->get(UrlGeneratorInterface::class);
        $urlGenService = self::getContainer()->get(RoomService::class);
        $this->assertTrue($client->getResponse()->isRedirect($urlGenService->joinUrl('a',$room,'Test User 123',true)));

        $buttonCrawlerNode = $crawler->selectButton('Im Browser beitreten');
        $form = $buttonCrawlerNode->form();
        $form['join_my_room[name]'] = 'Test User 123';
        $client->submit($form);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString("jwt: '".$urlGenService->generateJwt($room,$user,'Test User 123'),$client->getResponse()->getContent());
        self::assertStringContainsString( "displayName: 'Test User 123'",$client->getResponse()->getContent());
        self::assertStringContainsString( " roomName: '".$room->getUid()."'",$client->getResponse()->getContent());
    }
    public function test_hasNoStart_noModerator_NoLobby(): void
    {
        $client = static::createClient();
        $room = $this->getRoomByName('This Room has no participants and fixed room');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($room);
        $em->flush();
        $crawler = $client->request('GET', '/myRoom/start/' . $room->getUid());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', $room->getName());

        $buttonCrawlerNode = $crawler->selectButton('Mit der Elektron-App beitreten');
        $form = $buttonCrawlerNode->form();
        $form['join_my_room[name]'] = 'Test User 123';
        $client->submit($form);
        $urlGenerator = self::getContainer()->get(UrlGeneratorInterface::class);
        $urlGenService = self::getContainer()->get(RoomService::class);
        $this->assertTrue($client->getResponse()->isRedirect($urlGenService->joinUrl('a',$room,'Test User 123',false)));

        $buttonCrawlerNode = $crawler->selectButton('Im Browser beitreten');
        $form = $buttonCrawlerNode->form();
        $form['join_my_room[name]'] = 'Test User 123';
        $client->submit($form);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString("jwt: '".$urlGenService->generateJwt($room,null,'Test User 123'),$client->getResponse()->getContent());
        self::assertStringContainsString( "displayName: 'Test User 123'",$client->getResponse()->getContent());
        self::assertStringContainsString( " roomName: '".$room->getUid()."'",$client->getResponse()->getContent());
    }

    public function test_NoStart_isModerator_hasLobby(): void
    {
        $client = static::createClient();
        $userRepo = $this->getContainer()->get(UserRepository::class);
        $user = $userRepo->findOneBy(array('email' => 'test@local.de'));
        $client->loginUser($user);
        $room = $this->getRoomByName('This Room has no participants and fixed room and Lobby activated');
        $crawler = $client->request('GET', '/myRoom/start/' . $room->getUid());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', $room->getName());

        $buttonCrawlerNode = $crawler->selectButton('Mit der Elektron-App beitreten');
        $form = $buttonCrawlerNode->form();
        $form['join_my_room[name]'] = 'Test User 123';
        $client->submit($form);
        $urlGenService = self::getContainer()->get(RoomService::class);
        self::assertResponseIsSuccessful();
        $lobbyRepo = self::getContainer()->get(LobbyWaitungUserRepository::class);
        $lobbyUSer = $lobbyRepo->findBy(array('showName'=>'Test User 123'));
        self::assertIsInt(sizeof($lobbyUSer),1);
        self::assertStringContainsString("jwt: '".$urlGenService->generateJwt($room,$user,'Test User 123'),$client->getResponse()->getContent());
        self::assertStringNotContainsString('Bitte warten Sie. Der Moderator wurde informiert und lässt Sie eintreten.',$client->getResponse()->getContent());
        self::assertStringContainsString( "displayName: 'Test User 123'",$client->getResponse()->getContent());
        self::assertStringContainsString( " roomName: '".$room->getUid()."'",$client->getResponse()->getContent());
        self::assertStringContainsString( 'room/lobby/start/moderator/a/'.$room->getUidReal(),$client->getResponse()->getContent());

        $buttonCrawlerNode = $crawler->selectButton('Im Browser beitreten');
        $form = $buttonCrawlerNode->form();
        $form['join_my_room[name]'] = 'Test User 123';
        $client->submit($form);
        $lobbyUSer = $lobbyRepo->findBy(array('showName'=>'Test User 123'));
        self::assertIsInt(sizeof($lobbyUSer),2);
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString( 'room/lobby/start/moderator/a/'.$room->getUidReal(),$client->getResponse()->getContent());
        self::assertStringContainsString("jwt: '".$urlGenService->generateJwt($room,$user,'Test User 123'),$client->getResponse()->getContent());
        self::assertStringNotContainsString('Bitte warten Sie. Der Moderator wurde informiert und lässt Sie eintreten.',$client->getResponse()->getContent());
        self::assertStringContainsString( "displayName: 'Test User 123'",$client->getResponse()->getContent());
        self::assertStringContainsString( " roomName: '".$room->getUid()."'",$client->getResponse()->getContent());
    }
    public function test_NoStart_noModerator_hasLobby(): void
    {
        $client = static::createClient();
        $room = $this->getRoomByName('This Room has no participants and fixed room and Lobby activated');
        $crawler = $client->request('GET', '/myRoom/start/' . $room->getUid());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', $room->getName());

        $buttonCrawlerNode = $crawler->selectButton('Mit der Elektron-App beitreten');
        $form = $buttonCrawlerNode->form();
        $form['join_my_room[name]'] = 'Test User 123';
        $client->submit($form);
        $urlGenService = self::getContainer()->get(RoomService::class);
        self::assertResponseIsSuccessful();
        $lobbyRepo = self::getContainer()->get(LobbyWaitungUserRepository::class);
        $lobbyUSer = $lobbyRepo->findBy(array('showName'=>'Test User 123'));
        self::assertIsInt(sizeof($lobbyUSer),1);

        self::assertStringContainsString("var type = 'a';",$client->getResponse()->getContent());
        self::assertStringContainsString(" <script src='https://meet.jit.si2/external_api.js'></script>",$client->getResponse()->getContent());
        self::assertStringContainsString('Bitte warten Sie. Der Moderator wurde informiert und lässt Sie eintreten.',$client->getResponse()->getContent());
        self::assertStringNotContainsString("jwt",$client->getResponse()->getContent());

        $buttonCrawlerNode = $crawler->selectButton('Im Browser beitreten');
        $form = $buttonCrawlerNode->form();
        $form['join_my_room[name]'] = 'Test User 123';
        $client->submit($form);
        $lobbyUSer = $lobbyRepo->findBy(array('showName'=>'Test User 123'));
        self::assertIsInt(sizeof($lobbyUSer),2);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString("var type = 'b';",$client->getResponse()->getContent());
        self::assertStringContainsString('Bitte warten Sie. Der Moderator wurde informiert und lässt Sie eintreten.',$client->getResponse()->getContent());
        self::assertStringContainsString(" <script src='https://meet.jit.si2/external_api.js'></script>",$client->getResponse()->getContent());
        self::assertStringNotContainsString("jwt",$client->getResponse()->getContent());
    }
    public function getRoomByName($name)
    {
        $roomRepo = $this->getContainer()->get(RoomsRepository::class);
        $room = $roomRepo->findOneBy(array('name' => $name));
        return $room;
    }

    public function getUSerByEmail($name)
    {
        $userRepo = $this->getContainer()->get(UserRepository::class);
        $user = $userRepo->findOneBy(array('email' => $name));
        return $user;
    }
}
