<?php

use App\Enums\Role;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Organization;
use App\Models\Task;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

describe('comments', function () {
    it('lets anyone who can see a task comment on it', function () {
        $organization = Organization::factory()->create();
        $task = Task::factory()->for($organization)->create();
        $auditor = memberIn($organization, Role::Auditor);

        actingAs($auditor)
            ->post(route('tasks.comments.store', [$organization, $task]), ['body' => '  Is the supplier confirmed?  '])
            ->assertRedirect();

        expect($task->comments()->sole())->body->toBe('Is the supplier confirmed?')->author_id->toBe($auditor->id);
    });

    it('only lets the author delete a comment', function () {
        $organization = Organization::factory()->create();
        $task = Task::factory()->for($organization)->create();
        $author = memberIn($organization);
        $comment = inTenant($organization, fn () => $task->comments()->create(['author_id' => $author->id, 'body' => 'Mine']));

        actingAs($organization->owner)->delete(route('comments.destroy', [$organization, $comment]))->assertForbidden();
        actingAs($author)->delete(route('comments.destroy', [$organization, $comment]))->assertRedirect();

        expect(Comment::withoutOrganizationScope()->whereKey($comment->id)->exists())->toBeFalse();
    });
});

describe('attachments', function () {
    it('stores an upload privately under a random name and serves it to members', function () {
        Storage::fake('local');
        $organization = Organization::factory()->create();
        $task = Task::factory()->for($organization)->create();

        actingAs($organization->owner)
            ->post(route('tasks.attachments.store', [$organization, $task]), [
                'file' => UploadedFile::fake()->create('Quote ACME.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect();

        $attachment = inTenant($organization, fn () => Attachment::query()->sole());

        expect($attachment)
            ->original_name->toBe('Quote ACME.pdf')
            ->path->toStartWith("organizations/{$organization->id}/attachments/")
            ->path->not->toContain('Quote');
        Storage::disk('local')->assertExists($attachment->path);

        actingAs(memberIn($organization, Role::Auditor))
            ->get(route('attachments.download', [$organization, $attachment]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertDownload('Quote ACME.pdf');
    });

    it('rejects files whose contents do not match their type', function () {
        Storage::fake('local');
        $organization = Organization::factory()->create();
        $task = Task::factory()->for($organization)->create();

        // A real file on disk, so its type is sniffed from the content (the
        // testing fake would report a type based on the name alone).
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, '<?php system($_GET["c"]);');

        actingAs($organization->owner)
            ->post(route('tasks.attachments.store', [$organization, $task]), [
                'file' => new UploadedFile($path, 'invoice.pdf', null, null, true),
            ])
            ->assertSessionHasErrors(['file' => 'The file’s contents do not match its type. Upload a PDF, image, Office document, text, CSV or ZIP file.']);

        expect(Attachment::withoutOrganizationScope()->count())->toBe(0);
    });

    it('refuses files with a dangerous extension', function () {
        Storage::fake('local');
        $organization = Organization::factory()->create();
        $task = Task::factory()->for($organization)->create();

        actingAs($organization->owner)
            ->post(route('tasks.attachments.store', [$organization, $task]), [
                'file' => UploadedFile::fake()->create('shell.php', 1, 'text/plain'),
            ])
            ->assertSessionHasErrors('file');
    });

    it('forbids uploading to a task the member cannot edit', function () {
        Storage::fake('local');
        $organization = Organization::factory()->create();
        $task = Task::factory()->for($organization)->create();

        actingAs(memberIn($organization, Role::Auditor))
            ->post(route('tasks.attachments.store', [$organization, $task]), [
                'file' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
            ])
            ->assertForbidden();
    });

    it('returns 404 when downloading a file from another organization', function () {
        Storage::fake('local');
        $organization = Organization::factory()->create();
        $other = Organization::factory()->create();
        $task = Task::factory()->for($other)->create();

        actingAs($other->owner)->post(route('tasks.attachments.store', [$other, $task]), [
            'file' => UploadedFile::fake()->create('secret.pdf', 10, 'application/pdf'),
        ]);
        $attachment = inTenant($other, fn () => Attachment::query()->sole());

        actingAs($organization->owner)
            ->get(route('attachments.download', [$organization, $attachment]))
            ->assertNotFound();
    });

    it('deletes the file along with its record', function () {
        Storage::fake('local');
        $organization = Organization::factory()->create();
        $task = Task::factory()->for($organization)->create();

        actingAs($organization->owner)->post(route('tasks.attachments.store', [$organization, $task]), [
            'file' => UploadedFile::fake()->create('plan.pdf', 10, 'application/pdf'),
        ]);
        $attachment = inTenant($organization, fn () => Attachment::query()->sole());

        actingAs($organization->owner)->delete(route('attachments.destroy', [$organization, $attachment]))->assertRedirect();

        Storage::disk('local')->assertMissing($attachment->path);
        expect(Attachment::withoutOrganizationScope()->count())->toBe(0);
    });
});
