<?php
/**
 * Class that does a MySQL backup for a number of configured MySQLConfig instances.
 * Executes mysqldump with gzip option and writes the backup into a file.
 * The file is base64 encoded and send to a number of configured email recipients.
 * @author Alexander Herrmann <alex@zoe.vc>
 * @version 0.1.3
 */
class MySQLBackup {
	/**
	 * Array of MySQLConfig instances.
	 * @var array
	 */
	private $configs = array();
	
	/**
	 * Format of the date of the backup file.
	 * @var string
	 */
	private $dateFormat = 'Y-m-d';
	
	/**
	 * Extension of the backup file.
	 * @var string
	 */
	private $extension = '.gz';
	
	/**
	 * Flag if to delete the files after backup.
	 * Makes only sense if you send them per email.
	 * @var bool
	 */
	private $deleteFiles = false;
	
	/**
	 * Name of the to be used mailer instance.
	 * mail or smtp
	 * @var string
	 */
	private $mailer = '';
	
	/**
	 * The default email subject.
	 * @var string
	 */
	private $mailSubject = 'Backup';
	
	/**
	 * Parameters for mysqldump command.
	 * @var string
	 */
	private $opts = '--quick --lock-tables --add-drop-table';
	
	/**
	 * Execution path of the script.
	 * @var string
	 */
	private $path;
	
	/**
	 * Path to gzip.
	 * @var string
	 */
	private $pathGZip = '';
	
	/**
	 * The path to mysqldump.
	 * @var string
	 */
	private $pathMysqldump = '';
	
	/**
	 * Array of email addresses the backup will be sent to.
	 * @var array
	 */
	private $recipients = array();
	
	/**
	 * Default email of sender.
	 * @var string
	 */
	private $sender = 'backup@localhost';
	
	/**
	 * The smtp config values.
	 * host
	 * username
	 * password
	 * port
	 * @var array
	 */
	private $smtpConfig = array();
	
	/**
	 * Zipping parameter for mysqldump.
	 * @var string
	 */
	private $zip = 'gzip';
	
	/**
	 * Adds the values of a database to backup
	 * @param string $schema	the database schema
	 * @param string $username	the database username
	 * @param string $password	the database password
	 * @param string $host		the database host
	 * @return MySQLBackup
	 */
	public function addDatabaseToBackup($schema, $username, $password, $host = '') {
		$this->configs[] = array(
			$schema,
			$username,
			$password,
			$host
		);
		return $this;
	}
		
	/**
	 * Runs the mysqldump and sends the emails if set.
	 */
	public function backup() {
		// gather the paths to the created backup files for emailing!
		$attachmentPaths = array();
		
		// run the backup for each given config
		foreach ($this->configs as $config) {
			
			// extract config data
			$schema = $config[0];
			$username = $config[1];
			$password = $config[2];
			$host = $config[3];
		
			// create the path to the backup file
			$path = sprintf(
				'%s%s_%s.sql%s',
				$this->path,
				$schema,
				date($this->dateFormat),
				$this->extension
			);
			
			// save path for attaching
			$attachmentPaths[] = $path;
			
			// build the command
			$command = sprintf(
				'%smysqldump --user=\'%s\' --password=\'%s\'%s %s %s | %s > %s',
				$this->pathMysqldump,
				$username,
				$password,
				(strlen($host) > 0 ? ' --host=' . $host : ''),
				$schema,
				$this->opts,
				$this->pathGZip . $this->zip,
				$path
			);
			
			// execute the command
			exec($command);
			
			// reset variables
			$schema = $username = $password = $host = '';
		}
		
		// check mail prerequisites
		// mailer has to be defined and someone to send to
		if (strlen($this->mailer) > 0 && in_array($this->mailer, array('mail', 'smtp')) && count($this->recipients) > 0) {
			require_once 'lib/swift-4.0.6/swift_required.php';
			
			// select swift transport
			$transport = null;
			switch ($this->mailer) {
				case 'mail':
					$transport = Swift_MailTransport::newInstance();
					break;
				case 'smtp':
					$transport = Swift_SmtpTransport::newInstance($this->smtpConfig[0], $this->smtpConfig[3])
						->setUsername($this->smtpConfig[1])
						->setPassword($this->smtpConfig[2])
					;
					break;
			}
			
			// create the mailer
			$mailer = Swift_Mailer::newInstance($transport);
			
			// create the message
			$message = Swift_Message::newInstance()
				->setSubject($this->mailSubject)
				->setFrom(array($this->sender))
				->setTo($this->recipients)
				->setBody('')
			;
			
			// attach the backup files
			foreach ($attachmentPaths as $path) {
				if (file_exists($path)) {
					$message->attach(Swift_Attachment::fromPath($path));
				} else  {
					die('Path of backup file not found: ' . $path);
				}
			}
			
			// send the mail
			$result = $mailer->send($message);
		}
		
		// if the backup files shall be deleted, do so
		if ($this->deleteFiles) {
			foreach ($attachmentPaths as $path) {
				unlink($path);
			}
		}
	}
	
	/**
	 * Sets the flag for deleting the files after the backup.
	 * Only useful if you send them per email.
	 * @return MySQLBackup
	 */
	public function deleteFilesAfterBackup() {
		$this->deleteFiles = true;
		return $this;
	}
	
	/**
	 * Static class initializer.
	 * @return MySQLBackup
	 */
	public static function init() {
		return new MySQLBackup();
	}
	
	/**
	 * Sets the path to where to stores the backup files. Has to have trailing slash.
	 * E.g. /srv/myhost/mydir/
	 * @param string $path the destination path
	 * @return MySQLBackup
	 */
	public function setBackupFilesPath($path) {
		if (strlen($path) > 0) {
			$this->path = $path;
			if (substr($this->path, strlen($this->path) - 1, 1) !== '/') {
				$this->path . '/';
			}
			return $this;
		} else {
			die('No path for backup files given! (setBackupFilesPath($path))');
		}
	}
	
	/**
	 * Sets the given recipients as recipients.
	 * @param array $recipients	array of recipients
	 * @return MySQLBackup
	 */
	public function setEmailRecipients($recipients) {
		$this->recipients = $recipients;
		return $this;
	}
	
	/**
	 * Sets the email address the script will be sent from.
	 * Default value is "root@localhost"
	 * @param string $email email address (not validated!)
	 * @return MySQLBackup
	 */
	public function setEmailSender($email) {
		if (strlen($email) > 0) {
			$this->sender = $email;
		}
		return $this;
	}
	
	/**
	 * Sets the email subject.
	 * @param string $subject	the subject
	 * @return MySQLBackup
	 */
	public function setEmailSubject($subject) {
		if (strlen($subject) > 0) {
			$this->mailSubject = $subject;
		}
		return $this;
	}
	
	/**
	 * Sets the date format for the filename.
	 * @see date()
	 * @param string $format the format, default is Y-m-d
	 * @return MySQLBackup
	 */
	public function setFileDateFormat($format) {
		if (strlen($format) > 0) {
			$this->dateFormat = $format;
		}
		return $this;
	}
	
	/**
	 * Sets the path to the gzip executable. Has to have trailing slash.
	 * @param string $path the path to gzip
	 * @return MySQLBackup
	 */
	public function setGZipPath($path) {
		$this->pathGZip = $path;
		return $this;
	}
	
	/**
	 * Sets the name of the Swift mailer used for sending.
	 * @param string $mailer	the name of the mailer, either mail or smtp
	 * @return MySQLBackup
	 */
	public function setMailer($mailer) {
		$this->mailer = $mailer;
		return $this;
	}
	
	/**
	 * Sets the path to mysqldump. Has to have trailing slash.
	 * @param string $path the path to mysqldump
	 * @return MySQLBackup
	 */
	public function setMysqldumpPath($path) {
		$this->pathMysqldump = $path;
		return $this;
	}
	
	/**
	 * Sets the SMTP config.
	 * array('host', 'username', 'password', port)
	 * @param array $smtp	the config
	 * @return MySQLBackup
	 */
	public function setSMTPConfig($smtp) {
		$this->smtpConfig = $smtp;
		return $this;
	}
}
?>