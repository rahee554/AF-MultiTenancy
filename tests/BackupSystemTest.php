<?php

namespace ArtflowStudio\Tenancy\Tests;

use PHPUnit\Framework\TestCase;
use ArtflowStudio\Tenancy\Services\TenantBackupService;
use ArtflowStudio\Tenancy\Commands\Backup\TenantBackupCommand;
use ArtflowStudio\Tenancy\Commands\Backup\BackupManagementCommand;

class BackupSystemTest extends TestCase
{
    public function test_backup_service_exists()
    {
        $this->assertTrue(class_exists(TenantBackupService::class));
    }

    public function test_backup_commands_exist()
    {
        $this->assertTrue(class_exists(TenantBackupCommand::class));
        $this->assertTrue(class_exists(BackupManagementCommand::class));
    }

    public function test_backup_service_methods()
    {
        $reflection = new \ReflectionClass(TenantBackupService::class);
        
        $this->assertTrue($reflection->hasMethod('createBackup'));
        $this->assertTrue($reflection->hasMethod('restoreBackup'));
        $this->assertTrue($reflection->hasMethod('listTenantBackups'));
        $this->assertTrue($reflection->hasMethod('listAllBackups'));
        $this->assertTrue($reflection->hasMethod('cleanupOldBackups'));
    }

    public function test_backup_command_signatures()
    {
        $reflection = new \ReflectionClass(TenantBackupCommand::class);
        $signatureProperty = $reflection->getProperty('signature');
        $signatureProperty->setAccessible(true);
        
        // We can't instantiate without dependencies, but we can check the class structure
        $this->assertTrue($reflection->hasProperty('signature'));
        $this->assertTrue($reflection->hasProperty('description'));
    }

    public function test_backup_management_command_signatures()
    {
        $reflection = new \ReflectionClass(BackupManagementCommand::class);
        $signatureProperty = $reflection->getProperty('signature');
        $signatureProperty->setAccessible(true);
        
        $this->assertTrue($reflection->hasProperty('signature'));
        $this->assertTrue($reflection->hasProperty('description'));
    }

    public function test_backup_service_constants()
    {
        $reflection = new \ReflectionClass(TenantBackupService::class);
        $constants = $reflection->getConstants();
        
        // Check if we have expected backup types
        $this->assertArrayHasKey('BACKUP_TYPE_FULL', $constants);
        $this->assertArrayHasKey('BACKUP_TYPE_STRUCTURE', $constants);
    }
}
