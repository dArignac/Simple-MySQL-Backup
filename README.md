# Simple-MySQL-Backup

PHP script for backup of MySQL databases - uses the power of mysqldump and gzip.

## Features

  * Backup of databases and compression
  * Emailing of backup files

## Requirements

You need rights to execute the PHP "exec" command, and you need mysqldump and gzip installed.

## Usage

Download the script and put it somewhere on your server (let's assume "/somewhere/simple-mysql-backup/"). Create a folder where you'll store the backup files and set write permission for this folder (let's assume "/somewhere/backup-files/").

Create a new file (let's assume it is called "backup.php") and include the "backup-required.php" from the lib.

	require_once '/somewhere/simple-mysql-backup/backup-required.php';
	
Then create a backup for a table:

	MySQLBackup::init()
		->setBackupFilesPath('/somewhere/backup-files/')
		->addDatabaseToBackup('database_name', 'username', 'password') // replace the 3 parameters with your values
		->backup()
	;
	
Running the backup.php will store the files to "/somewhere/backup-files".

If you want to email the backups and have them deleted, choose between 2 mailing options:

  * mail: using the php mailer
  * smtp: using an smtp mail account for sending (recommended)
  
then adjust your config:

	MySQLBackup::init()
		->setBackupFilesPath('/somewhere/backup-files/')
		->addDatabaseToBackup('database_name', 'username', 'password') // replace the 3 parameters with your values
		->setMailer('smtp') // smtp or mail
		->setSMTPConfig(array('smtp.example.com', 'smtp_user', 'smtp_password', 25)) // replace with your values
		->setEmailSender('backup@example.com') // set the sender address for the email
		->setEmailSubject('Backup of database') // set an email subject
		->setEmailRecipients(array('johndoe@example.com', 'janedoe@example.com')) // add some recipients for the email
		->deleteFilesAfterBackup() // deletes the files after sending
		->backup()
	;
	
The default filename is <schema>_<date>.sql.gz. <date> is by default "Y-m-d", you can change this by setting a different date format:

	MySQLBackup::init()
		...
		->setFileDateFormat('d.m.Y')
		->backup()
	;
	
If mysqldump or gzip are not within the path, you can specify their location:

	MySQLBackup::init()
		...
		->setMysqldumpPath('/path/to/mysqldump/') // path to the folder, not the executable! With trailing slash!
		->setGZipPath('/path/to/gzip/') // path to the folder, not the executable! With trailing slash!git status
		->backup()
	;