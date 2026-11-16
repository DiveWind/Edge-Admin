<?php

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('uploads are recorded as attachments', function () {
    Storage::fake('public');
    $user = actingAsUser(User::factory()->create());

    $response = $this->postJson('/api/uploads', ['file' => UploadedFile::fake()->create('报告.pdf', 20, 'application/pdf')])
        ->assertCreated()
        ->assertJsonPath('data.name', '报告.pdf')
        ->assertJsonPath('data.mime', 'application/pdf')
        ->assertJsonPath('data.uploaded_by', $user->id)
        ->assertJsonStructure(['data' => ['id', 'url', 'path', 'name', 'mime', 'size', 'created_at']]);

    $attachment = Attachment::query()->findOrFail($response->json('data.id'));
    expect($attachment->path)->toBe($response->json('data.path'));
    Storage::disk('public')->assertExists($attachment->path);
});

test('upload extensions and size come from configuration', function () {
    Storage::fake('public');
    actingAsUser(User::factory()->create());
    config(['edge.uploads.extensions' => ['png'], 'edge.uploads.max_size' => 10]);

    $this->postJson('/api/uploads', ['file' => UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')])->assertUnprocessable();
    $this->postJson('/api/uploads', ['file' => UploadedFile::fake()->image('big.png')->size(20)])->assertUnprocessable();
    $this->postJson('/api/uploads', ['file' => UploadedFile::fake()->image('ok.png')->size(5)])->assertCreated();
});

test('attachments can be listed with filters and uploader', function () {
    actingAsUser(userWithPermissions(['system.attachment.view']));
    $uploader = User::factory()->create();
    Attachment::factory()->create(['name' => 'logo.png', 'uploaded_by' => $uploader->id]);
    Attachment::factory()->create(['name' => 'contract.pdf', 'mime' => 'application/pdf']);

    $this->getJson('/api/attachments?name=logo')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uploader', ['id' => $uploader->id, 'name' => $uploader->name]);
    $this->getJson('/api/attachments?mime=pdf')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.uploader', null);
    $this->getJson("/api/attachments?uploaded_by={$uploader->id}")->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/attachments?created_from='.today()->toDateString())->assertOk()->assertJsonCount(2, 'data');
});

test('deleting an attachment removes the stored file', function () {
    Storage::fake('public');
    actingAsUser(userWithPermissions(['system.attachment.delete']));
    Storage::disk('public')->put('uploads/x.png', 'content');
    $attachment = Attachment::factory()->create(['path' => 'uploads/x.png']);

    $this->deleteJson("/api/attachments/{$attachment->id}")->assertNoContent();

    Storage::disk('public')->assertMissing('uploads/x.png');
    expect(Attachment::query()->count())->toBe(0);
});

test('attachment management requires permissions', function () {
    actingAsUser(User::factory()->create());

    $this->getJson('/api/attachments')->assertForbidden();
    $this->deleteJson('/api/attachments/'.Attachment::factory()->create()->id)->assertForbidden();
});
