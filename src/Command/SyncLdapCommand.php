<?php

namespace App\Command;

use App\dataType\LdapType;
use App\Entity\User;
use App\Service\ldap\LdapService;
use App\Service\ldap\LdapUserService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Exception\InvalidCredentialsException;
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\Ldap\Exception\NotBoundException;
use Symfony\Component\Ldap\Ldap;

class SyncLdapCommand extends Command
{
    protected static $defaultName = 'app:ldap:sync';
    protected static $defaultDescription = 'This commands syncs a ldap server with users database';
    private $ldapService;

    public function __construct(LdapService $ldapService, string $name = null)
    {
        parent::__construct($name);
        $this->ldapService = $ldapService;
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Activate Dry-Run. Not writing into the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryrun = $input->getOption('dry-run');
        if ($dryrun) {
            $io->info('Dryrun is activated. No databases changes are made');
        }

        $count = 0;
        $result = array();
        $io->info('We test the all LDAP connections: ');
        $error = false;
        if (!$this->ldapService->initLdap($io)) {
            return Command::FAILURE;
        };


        $numberUsers = 0;

        foreach ($this->ldapService->getLdaps() as $data) {

            $resTmp = null;
            try {
                $resTmp = $this->ldapService->fetchLdap($data, $dryrun);
            } catch (LdapException $e) {
                $error = true;
                $io->error('Fehler in LDAP: ' . $data->getUrl());
                $io->error('Fehler: ' . $e->getMessage());

            } catch (NotBoundException $e) {
                $error = true;
                $io->error('Fehler in LDAP-Bound: ' . $data->getUrl());
                $io->error('Fehler: ' . $e->getMessage());
            }

            if ($resTmp !== null) {
                $result[] = $resTmp;
            }

            $table = new Table($output);
            $table->setHeaders(['email', 'uid', 'dn', 'rdn']);
            $table->setHeaderTitle($data->getUrl());
            $table->setStyle('borderless');
            if (is_array($resTmp['user'])) {
                foreach ($resTmp['user'] as $data2) {
                    $numberUsers++;
                    $table->addRow([$data2->getEmail(), $data2->getUserName(), $data2->getLdapUserProperties()->getLdapDn(), $data2->getLdapUserProperties()->getRdn()]);
                }
            }
            $table->render();
        }


        $io->info('We found # users: ' . $numberUsers);
        if ($error === false) {
            $io->success('All LDAPS could be synced correctly');
            return Command::SUCCESS;
        } else {
            $io->error('There was an error. Check the output above');
            return Command::FAILURE;
        }

    }
}
