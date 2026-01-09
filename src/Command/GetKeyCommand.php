<?php

declare(strict_types=1);

namespace Satispay\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;

/**
 * GetKey command.
 */
class GetKeyCommand extends Command
{
    /**
     * Hook method for defining this command's option parser.
     *
     * @see https://book.cakephp.org/4/en/console-commands/commands.html#defining-arguments-and-options
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser The built parser.
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);

        return $parser;
    }

    /**
     * Implement this method with your command's logic.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return int|null|void The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $token = Configure::read('Satispay.token');
        $sandbox = Configure::read('Satispay.sandbox', false);
        $io->out("Authenticating with Satispay to get keys... token: $token, sandbox: $sandbox ."); 

        \SatispayGBusiness\Api::setSandbox($sandbox);
        $authentication = \SatispayGBusiness\Api::authenticateWithToken($token);

        $publicKey = $authentication->publicKey;
        $privateKey = $authentication->privateKey;
        $keyId = $authentication->keyId;

        file_put_contents(CONFIG . conf_path() . '/satispay-authentication.json', json_encode([
            'public_key' => $publicKey,
            'private_key' => $privateKey,
            'key_id' => $keyId,
            'sandbox' => $sandbox
        ], JSON_PRETTY_PRINT));

        // Non scrive bene la configurazione, commento per ora
        // Configure::write('Satispay.public_key', $publicKey);
        // Configure::write('Satispay.private_key', $privateKey);
        // Configure::write('Satispay.key_id', $keyId);
        // Configure::write('Satispay.sandbox', $sandbox);
        // Configure::dump('satispay', 'default');
        
        $io->out('Satispay keys saved to config and authentication.json');
    }
}
