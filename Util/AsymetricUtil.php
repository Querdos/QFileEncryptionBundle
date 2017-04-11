<?php
/**
 * Created by Hamza ESSAYEGH
 * User: querdos
 * Date: 4/9/17
 * Time: 6:31 PM
 */

namespace Querdos\QFileEncryptionBundle\Util;


use Querdos\QFileEncryptionBundle\Entity\QFile;
use Querdos\QFileEncryptionBundle\Entity\QKey;
use Querdos\QFileEncryptionBundle\Manager\QFileManager;
use Querdos\QFileEncryptionBundle\Manager\QKeyManager;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoder;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\VarDumper\VarDumper;

class AsymetricUtil
{
    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var string
     */
    private $gnupg_home;

    /**
     * @var QKeyManager
     */
    private $qkeyManager;

    /**
     * @var QFileManager
     */
    private $qFileManager;

    /**
     * @var UserPasswordEncoder
     */
    private $passwordEncoder;

    /**
     * Generate a keypair with the gien recipient,
     * passphrase and username
     *
     * @param string        $recipient
     * @param string        $passphrase
     * @param UserInterface $user
     */
    public function generate_key($recipient, $passphrase, UserInterface $user)
    {
        // checking recipient <> null
        if (null === $recipient || 0 == strlen($recipient)) {
            throw new Exception("Recipient cannot be null or empty. (value = {$recipient})");
        }

        // checking passphrase <> null
        if (null === $passphrase || 0 == strlen($passphrase)) {
            throw new Exception("Passphrase cannot be null or empty. (value = {$passphrase})");
        }

        // checking username <> null
        if (null === $user->getUsername() || 0 == strlen($user->getUsername())) {
            throw new Exception("Username cannot be null or empty. (value = {$user->getUsername()})");
        }

        // checking that user hasn't an existing key pair
        if (null !== $this->qkeyManager->findByUsername($user->getUsername())) {
            throw new Exception("User already have a saved key pair.");
        }

        // creating application with current kernel
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        // creating input
        $input = new ArrayInput(array(
            'command'       => 'qfe:gen-key',
            '--username'    => $user->getUsername(),
            '--recipient'   => $recipient,
            '--passphrase'  => $passphrase
        ));

        // Creating output
        // TODO: handle errors
        $output = new NullOutput();

        // running the key generation
        $application->run($input, $output);
    }

    /**
     * Encrypt the given file
     *
     * @param string $filePath
     * @param QKey   $qkey
     */
    public function encrypt_file($filePath, QKey $qkey)
    {
        // checking file path
        if (null === $filePath || !file_exists($filePath)) {
            throw new Exception("No valid file specified (value = {$filePath})");
        }

        // checking user has a key pair
        if (null === $this->qkeyManager->findByUsername($qkey->getUsername())) {
            throw new Exception("No key pair associated with {$qkey->getUsername()}");
        }

        // checking recipient
        if (null === $qkey->getRecipient()) {
            throw new Exception("No recipient specified");
        }

        // creating application with kernel
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        // creating input for encryption
        $input = new ArrayInput(array(
            'command' => 'qfe:encrypt',
            '--username' => $qkey->getUsername(),
            '--recipient' => $qkey->getRecipient(),
            'file' => $filePath
        ));

        // creating null output
        // TODO: Handle error
        $output = new NullOutput();

        // running application
        $application->run($input, $output);
    }

    /**
     * Decrypt the given file
     *
     * @param QFile  $qfile
     * @param QKey   $qkey
     * @param string $passphrase
     *
     * @return BinaryFileResponse|null
     */
    public function decrypt_file(QFile $qfile, QKey $qkey, $passphrase)
    {
        // checking passphrase
        if (!password_verify($passphrase, $qkey->getPassphrase())) {
            throw new Exception("Invalid passphrase");
        }

        // creating application with current kernel
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        // creating input array
        $input = new ArrayInput(array(
            'command'       => 'qfe:decrypt',
            '--username'    => $qkey->getUsername(),
            '--recipient'   => $qkey->getRecipient(),
            '--passphrase'  => $passphrase,
            'file'          => "{$qfile->getPath()}/{$qfile->getFilename()}.enc",
        ));

        // TODO: handle error
        $output = new NullOutput();
        $application->run($input, $output);

        // creating the response
        $response = new BinaryFileResponse("/tmp/{$qfile->getOriginalName()}");
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $qfile->getOriginalName());

        return $response;
    }

    /**
     * @param $gnupg_home
     */
    public function setGnupgHome($gnupg_home)
    {
        $this->gnupg_home = $gnupg_home;
    }

    /**
     * @param QKeyManager $manager
     */
    public function setQKeyManager($manager)
    {
        $this->qkeyManager = $manager;
    }

    /**
     * @param UserPasswordEncoder $encoder
     */
    public function setPasswordEncoder($encoder)
    {
        $this->passwordEncoder = $encoder;
    }

    /**
     * @param QFileManager $manager
     */
    public function setQFileManager($manager)
    {
        $this->qFileManager = $manager;
    }

    /**
     * @param Kernel $kernel
     *
     * @return AsymetricUtil
     */
    public function setKernel($kernel)
    {
        $this->kernel = $kernel;
        return $this;
    }
}