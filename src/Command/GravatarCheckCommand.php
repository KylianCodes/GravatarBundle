<?php

declare(strict_types=1);

namespace KylianCodes\GravatarBundle\Command;

use KylianCodes\GravatarBundle\Service\GravatarService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI command to check whether an email has a Gravatar account.
 */
#[AsCommand(
    name: 'gravatar:check',
    description: 'Check if an email address has a Gravatar account',
)]
class GravatarCheckCommand extends Command
{
    public function __construct(private readonly GravatarService $gravatarService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'The email address to check');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        $io->title('Gravatar Check');
        $io->text('Email: <info>'.$email.'</info>');

        if (!$this->gravatarService->exists($email)) {
            $io->warning('No Gravatar account found for this email.');

            return Command::SUCCESS;
        }

        $io->success('Gravatar account found!');
        $io->text('URL: <href='.$this->gravatarService->getUrl($email).'>'.$this->gravatarService->getUrl($email).'</>');

        $profile = $this->gravatarService->getProfile($email);

        if ($profile !== null) {
            $io->section('Profile');

            if (!empty($profile['display_name'])) {
                $io->text('Name:    '.$profile['display_name']);
            }

            if (!empty($profile['description'])) {
                $io->text('Bio:     '.$profile['description']);
            }

            if (!empty($profile['links'])) {
                $io->text('Links:');
                foreach ($profile['links'] as $link) {
                    $label = $link['label'] ?? '';
                    $url = $link['url'] ?? '';
                    $io->text('  - '.$label.': '.$url);
                }
            }
        }

        return Command::SUCCESS;
    }
}
