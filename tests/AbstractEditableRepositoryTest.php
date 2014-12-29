<?php

/*
 * This file is part of the puli/repository package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Repository\Tests;

use Puli\Repository\Api\EditableRepository;
use Puli\Repository\Api\ResourceRepository;
use Puli\Repository\Resource\Collection\ArrayResourceCollection;
use Puli\Repository\Tests\Resource\TestDirectory;
use Puli\Repository\Tests\Resource\TestFile;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractEditableRepositoryTest extends AbstractRepositoryTest
{
    /**
     * @var EditableRepository
     */
    protected $repo;

    /**
     * @param ResourceRepository $backend
     *
     * @return EditableRepository
     */
    abstract protected function createEditableRepository(ResourceRepository $backend = null);

    protected function setUp()
    {
        parent::setUp();

        $this->repo = $this->createEditableRepository();
    }

    public function testRootIsEmptyBeforeAdding()
    {
        $root = $this->repo->get('/');

        $this->assertInstanceOf('Puli\Repository\Api\Resource\Resource', $root);
        $this->assertCount(0, $root->listChildren());
        $this->assertSame('/', $root->getPath());
    }

    public function testAddFile()
    {
        $this->repo->add('/webmozart/puli', new TestDirectory());
        $this->repo->add('/webmozart/puli/file', new TestFile());

        $dir = $this->repo->get('/webmozart/puli');
        $file = $this->repo->get('/webmozart/puli/file');

        $this->assertInstanceOf('Puli\Repository\Api\Resource\Resource', $dir);
        $this->assertSame('/webmozart/puli', $dir->getPath());
        $this->assertSame($this->repo, $dir->getRepository());

        $this->assertInstanceOf('Puli\Repository\Api\Resource\BodyResource', $file);
        $this->assertSame('/webmozart/puli/file', $file->getPath());
        $this->assertSame($this->repo, $file->getRepository());
        $this->assertSame(TestFile::BODY, $file->getBody());
    }

    public function testAddDot()
    {
        $this->repo->add('/webmozart/puli/file/.', new TestFile());

        $file = $this->repo->get('/webmozart/puli/file');

        $this->assertInstanceOf('Puli\Repository\Api\Resource\BodyResource', $file);
        $this->assertSame('/webmozart/puli/file', $file->getPath());
    }

    public function testAddDotDot()
    {
        $this->repo->add('/webmozart/puli/file/..', new TestFile());

        $file = $this->repo->get('/webmozart/puli');

        $this->assertInstanceOf('Puli\Repository\Api\Resource\BodyResource', $file);
        $this->assertSame('/webmozart/puli', $file->getPath());
    }

    public function testAddTrimsTrailingSlash()
    {
        $this->repo->add('/webmozart/puli/file/', new TestFile());

        $file = $this->repo->get('/webmozart/puli/file');

        $this->assertInstanceOf('Puli\Repository\Api\Resource\BodyResource', $file);
        $this->assertSame('/webmozart/puli/file', $file->getPath());
    }

    public function testAddCollection()
    {
        $this->repo->add('/webmozart/puli', new ArrayResourceCollection(array(
            new TestFile('/file1'),
            new TestFile('/file2'),
        )));

        $file1 = $this->repo->get('/webmozart/puli/file1');
        $file2 = $this->repo->get('/webmozart/puli/file2');

        $this->assertInstanceOf('Puli\Repository\Api\Resource\BodyResource', $file1);
        $this->assertSame('/webmozart/puli/file1', $file1->getPath());

        $this->assertInstanceOf('Puli\Repository\Api\Resource\BodyResource', $file2);
        $this->assertSame('/webmozart/puli/file2', $file2->getPath());
    }

    public function testAddPathFromBackend()
    {
        $backend = $this->getMock('Puli\Repository\Api\ResourceRepository');
        $backendFile = new TestFile();
        $backendFile->attachTo($backend, '/dir/file');

        $backend->expects($this->once())
            ->method('get')
            ->with('/dir/file')
            ->will($this->returnValue($backendFile));

        $repo = $this->createEditableRepository($backend);
        $repo->add('/webmozart/puli/file', '/dir/file');

        // Backend resource was not modified
        $this->assertSame('/dir/file', $backendFile->getPath());
        $this->assertSame($backend, $backendFile->getRepository());

        $file = $repo->get('/webmozart/puli/file');

        $this->assertInstanceOf('Puli\Repository\Api\Resource\BodyResource', $file);
        $this->assertSame('/webmozart/puli/file', $file->getPath());
        $this->assertSame($repo, $file->getRepository());
        $this->assertSame(TestFile::BODY, $file->getBody());
    }

    public function testAddGlobFromBackendSingleMatch()
    {
        $backend = $this->getMock('Puli\Repository\Api\ResourceRepository');
        $backendFile = new TestFile();
        $backendFile->attachTo($backend, '/dir/file');

        $backend->expects($this->once())
            ->method('find')
            ->with('/dir/*')
            ->will($this->returnValue(new ArrayResourceCollection(array($backendFile))));

        $repo = $this->createEditableRepository($backend);
        $repo->add('/webmozart/puli', '/dir/*');

        // Backend resources were not modified
        $this->assertSame('/dir/file', $backendFile->getPath());
        $this->assertSame($backend, $backendFile->getRepository());

        $file = $repo->get('/webmozart/puli/file');

        $this->assertInstanceOf('Puli\Repository\Api\Resource\BodyResource', $file);
        $this->assertSame('/webmozart/puli/file', $file->getPath());
        $this->assertSame($repo, $file->getRepository());
        $this->assertSame(TestFile::BODY, $file->getBody());
    }

    public function testAddGlobFromBackendManyMatches()
    {
        $backend = $this->getMock('Puli\Repository\Api\ResourceRepository');
        $backendFile1 = new TestFile();
        $backendFile1->attachTo($backend, '/dir/file1');
        $backendFile2 = new TestFile();
        $backendFile2->attachTo($backend, '/dir/file2');

        $backend->expects($this->once())
            ->method('find')
            ->with('/dir/*')
            ->will($this->returnValue(new ArrayResourceCollection(array($backendFile1, $backendFile2))));

        $repo = $this->createEditableRepository($backend);
        $repo->add('/webmozart/puli', '/dir/*');

        // Backend resources were not modified
        $this->assertSame('/dir/file1', $backendFile1->getPath());
        $this->assertSame($backend, $backendFile1->getRepository());
        $this->assertSame('/dir/file2', $backendFile2->getPath());
        $this->assertSame($backend, $backendFile2->getRepository());

        $file1 = $repo->get('/webmozart/puli/file1');
        $file2 = $repo->get('/webmozart/puli/file2');

        $this->assertInstanceOf('Puli\Repository\Api\Resource\BodyResource', $file1);
        $this->assertSame('/webmozart/puli/file1', $file1->getPath());
        $this->assertSame($repo, $file1->getRepository());
        $this->assertSame(TestFile::BODY, $file1->getBody());

        $this->assertInstanceOf('Puli\Repository\Api\Resource\BodyResource', $file2);
        $this->assertSame('/webmozart/puli/file2', $file2->getPath());
        $this->assertSame($repo, $file2->getRepository());
        $this->assertSame(TestFile::BODY, $file2->getBody());
    }

    public function testAddRoot()
    {
        $this->repo->add('/', new TestDirectory('/', array(
            new TestDirectory('/webmozart', array(
                new TestFile('/webmozart/file'),
            )),
        )));

        $root = $this->repo->get('/');
        $dir = $this->repo->get('/webmozart');
        $file = $this->repo->get('/webmozart/file');

        $this->assertInstanceOf('Puli\Repository\Api\Resource\Resource', $root);
        $this->assertSame('/', $root->getPath());
        $this->assertSame($this->repo, $root->getRepository());
        $this->assertCount(1, $root->listChildren());

        $this->assertInstanceOf('Puli\Repository\Api\Resource\Resource', $dir);
        $this->assertSame('/webmozart', $dir->getPath());
        $this->assertSame($this->repo, $dir->getRepository());
        $this->assertCount(1, $dir->listChildren());

        $this->assertInstanceOf('Puli\Repository\Api\Resource\BodyResource', $file);
        $this->assertSame('/webmozart/file', $file->getPath());
        $this->assertSame($this->repo, $file->getRepository());
        $this->assertSame(TestFile::BODY, $file->getBody());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddExpectsAbsolutePath()
    {
        $this->repo->add('webmozart', new TestDirectory());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddExpectsNonEmptyPath()
    {
        $this->repo->add('', new TestDirectory());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddExpectsStringPath()
    {
        $this->repo->add(new \stdClass(), new TestDirectory());
    }

    /**
     * @expectedException \Puli\Repository\Api\UnsupportedResourceException
     */
    public function testAddExpectsResource()
    {
        $this->repo->add('/webmozart', new \stdClass());
    }

    public function testRemoveFile()
    {
        $this->repo->add('/webmozart/puli/file1', new TestFile());
        $this->repo->add('/webmozart/puli/file2', new TestFile());

        $this->assertTrue($this->repo->contains('/webmozart'));
        $this->assertTrue($this->repo->contains('/webmozart/puli'));
        $this->assertTrue($this->repo->contains('/webmozart/puli/file1'));
        $this->assertTrue($this->repo->contains('/webmozart/puli/file2'));

        $this->assertSame(1, $this->repo->remove('/webmozart/puli/file1'));

        $this->assertTrue($this->repo->contains('/webmozart'));
        $this->assertTrue($this->repo->contains('/webmozart/puli'));
        $this->assertFalse($this->repo->contains('/webmozart/puli/file1'));
        $this->assertTrue($this->repo->contains('/webmozart/puli/file2'));
    }

    public function testRemoveMany()
    {
        $this->repo->add('/webmozart/puli/file1', new TestFile());
        $this->repo->add('/webmozart/puli/file2', new TestFile());

        $this->assertTrue($this->repo->contains('/webmozart/puli'));
        $this->assertTrue($this->repo->contains('/webmozart/puli/file1'));
        $this->assertTrue($this->repo->contains('/webmozart/puli/file2'));

        $this->assertSame(2, $this->repo->remove('/webmozart/puli/file*'));

        $this->assertTrue($this->repo->contains('/webmozart/puli'));
        $this->assertFalse($this->repo->contains('/webmozart/puli/file1'));
        $this->assertFalse($this->repo->contains('/webmozart/puli/file2'));
    }

    public function provideDirectoryGlob()
    {
        return array(
            array('/webmozart/puli'),
            array('/webmozart/pu*'),
        );
    }

    /**
     * @dataProvider provideDirectoryGlob
     */
    public function testRemoveDirectory($glob)
    {
        $this->repo->add('/webmozart/puli/file1', new TestFile());
        $this->repo->add('/webmozart/puli/file2', new TestFile());

        $this->assertTrue($this->repo->contains('/webmozart'));
        $this->assertTrue($this->repo->contains('/webmozart/puli'));
        $this->assertTrue($this->repo->contains('/webmozart/puli/file1'));
        $this->assertTrue($this->repo->contains('/webmozart/puli/file2'));

        $this->assertSame(3, $this->repo->remove($glob));

        $this->assertTrue($this->repo->contains('/webmozart'));
        $this->assertFalse($this->repo->contains('/webmozart/puli'));
        $this->assertFalse($this->repo->contains('/webmozart/puli/file1'));
        $this->assertFalse($this->repo->contains('/webmozart/puli/file2'));
    }

    public function testRemoveDot()
    {
        $this->repo->add('/webmozart/puli/file1', new TestFile());
        $this->repo->add('/webmozart/puli/file2', new TestFile());

        $this->assertTrue($this->repo->contains('/webmozart'));
        $this->assertTrue($this->repo->contains('/webmozart/puli'));
        $this->assertTrue($this->repo->contains('/webmozart/puli/file1'));
        $this->assertTrue($this->repo->contains('/webmozart/puli/file2'));

        $this->repo->remove('/webmozart/puli/.');

        $this->assertTrue($this->repo->contains('/webmozart'));
        $this->assertFalse($this->repo->contains('/webmozart/puli'));
        $this->assertFalse($this->repo->contains('/webmozart/puli/file1'));
        $this->assertFalse($this->repo->contains('/webmozart/puli/file2'));
    }

    public function testRemoveDotDot()
    {
        $this->repo->add('/webmozart/puli/file1', new TestFile());
        $this->repo->add('/webmozart/puli/file2', new TestFile());

        $this->assertTrue($this->repo->contains('/'));
        $this->assertTrue($this->repo->contains('/webmozart'));
        $this->assertTrue($this->repo->contains('/webmozart/puli'));
        $this->assertTrue($this->repo->contains('/webmozart/puli/file1'));
        $this->assertTrue($this->repo->contains('/webmozart/puli/file2'));

        $this->repo->remove('/webmozart/puli/..');

        $this->assertTrue($this->repo->contains('/'));
        $this->assertFalse($this->repo->contains('/webmozart'));
        $this->assertFalse($this->repo->contains('/webmozart/puli'));
        $this->assertFalse($this->repo->contains('/webmozart/puli/file1'));
        $this->assertFalse($this->repo->contains('/webmozart/puli/file2'));
    }

    public function testRemoveDiscardsTrailingSlash()
    {
        $this->repo->add('/webmozart/puli/file1', new TestFile());
        $this->repo->add('/webmozart/puli/file2', new TestFile());

        $this->assertTrue($this->repo->contains('/webmozart/puli'));
        $this->assertTrue($this->repo->contains('/webmozart/puli/file1'));
        $this->assertTrue($this->repo->contains('/webmozart/puli/file2'));

        $this->repo->remove('/webmozart/puli/');

        $this->assertFalse($this->repo->contains('/webmozart/puli'));
        $this->assertFalse($this->repo->contains('/webmozart/puli/file1'));
        $this->assertFalse($this->repo->contains('/webmozart/puli/file2'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCannotRemoveRoot()
    {
        $this->repo->remove('/');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRemoveInterpretsConsecutiveSlashesAsRoot()
    {
        $this->repo->remove('///');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRemoveExpectsAbsolutePath()
    {
        $this->repo->remove('webmozart');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRemoveExpectsNonEmptyPath()
    {
        $this->repo->remove('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRemoveExpectsStringPath()
    {
        $this->repo->remove(new \stdClass());
    }
}
