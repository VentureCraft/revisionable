<?php

namespace Venturecraft\Revisionable\Tests;

use Venturecraft\Revisionable\Tests\Models\User;

class RevisionTest extends \Orchestra\Testbench\TestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->loadLaravelMigrations(['--database' => 'testing']);

        // call migrations specific to our tests, e.g. to seed the db
        // the path option should be an absolute path.
        $this->loadMigrationsFrom([
            '--database' => 'testing',
            '--path' => realpath(__DIR__.'/../src/migrations'),
        ]);
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', array(
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ));
    }

    /**
     * Test we can interact with the database
     */
    public function testUsersTable()
    {
        User::create([
            'name' => 'James Judd',
            'email' => 'james.judd@revisionable.test',
            'password' => \Hash::make('456'),
        ]);

        $users = User::findOrFail(1);
        $this->assertEquals('james.judd@revisionable.test', $users->email);
        $this->assertTrue(\Hash::check('456', $users->password));
    }

    /**
     * Make sure revisions are created
     */
    public function testRevisionsStored()
    {
        $user = User::create([
            'name' => 'James Judd',
            'email' => 'james.judd@revisionable.test',
            'password' => \Hash::make('456'),
        ]);

        // change to my nickname
        $user->update([
            'name' => 'Judd'
        ]);

        // change to my forename
        $user->update([
            'name' => 'James'
        ]);

        // we should have two revisions to my name
        $this->assertCount(2, $user->revisionHistory);
    }

    /**
     * Make sure additional fields are saved with revision
     */
    public function testRevisionStoredAdditionalFields()
    {
        $this->loadMigrationsFrom([
            '--database' => 'testing',
            '--path' => realpath(__DIR__.'/migrations'),
        ]);

        $this->app['config']->set('revisionable.additional_fields', ['additional_field']);

        $user = User::create([
            'name' => 'James Judd',
            'email' => 'james.judd@revisionable.test',
            'additional_field' => 678,
            'password' => \Hash::make('456'),
        ]);


        // change to my nickname
        $user->update([
            'name' => 'Judd'
        ]);

        // we should have two revisions to my name
        $this->assertCount(1, $user->revisionHistory);

        $this->assertEquals(678, $user->revisionHistory->first()->additional_field);
    }

    /**
     * Make sure additional fields without values don't break
     */
    public function testRevisionSkipsAdditionalFieldsWhenNotAvailable()
    {
        $this->loadMigrationsFrom([
            '--database' => 'testing',
            '--path' => realpath(__DIR__.'/migrations'),
        ]);

        $this->app['config']->set('revisionable.additional_fields', ['additional_field']);

        $user = User::create([
            'name' => 'James Judd',
            'email' => 'james.judd@revisionable.test',
            'password' => \Hash::make('456'),
        ]);


        // change to my nickname
        $user->update([
            'name' => 'Judd'
        ]);

        // we should have two revisions to my name
        $this->assertCount(1, $user->revisionHistory);

        $this->assertNull($user->revisionHistory->first()->additional_field);
    }

    /**
     * Make sure additional fields which don't exist on the model still save revision
     */
    public function testRevisionSkipsAdditionalFieldsWhenMisconfigured()
    {
        $this->loadMigrationsFrom([
            '--database' => 'testing',
            '--path' => realpath(__DIR__.'/migrations'),
        ]);

        $this->app['config']->set('revisionable.additional_fields', ['unknown_field']);

        $user = User::create([
            'name' => 'James Judd',
            'email' => 'james.judd@revisionable.test',
            'password' => \Hash::make('456'),
        ]);


        // change to my nickname
        $user->update([
            'name' => 'Judd'
        ]);

        // we should have two revisions to my name
        $this->assertCount(1, $user->revisionHistory);
    }
}
