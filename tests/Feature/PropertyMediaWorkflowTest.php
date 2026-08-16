<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PropertyMediaWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_record_shows_all_registered_data_and_renders_each_media_type(): void
    {
        [$admin, $property] = $this->propertyFixture();

        $image = $property->media()->create([
            'mime_type' => 'image/png',
            'media_base64' => base64_encode($this->pngContents()),
            'sort_order' => 0,
        ]);
        $video = $property->media()->create([
            'mime_type' => 'video/mp4',
            'media_base64' => base64_encode($this->mp4Contents()),
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.properties.show', $property))
            ->assertOk()
            ->assertSee('Dados do imóvel')
            ->assertSee('Comercial')
            ->assertSee('Sala ampla para atendimento.')
            ->assertSee('R$ 2.350,50')
            ->assertSee('85,75 m²')
            ->assertSee('Endereço')
            ->assertSee('Avenida Central')
            ->assertSee('Sala 3')
            ->assertSee('50000-100')
            ->assertSee('Mídias do imóvel')
            ->assertSee('Imagem 1')
            ->assertSee('Vídeo 2')
            ->assertSee(route('admin.properties.media.store', $property), false)
            ->assertSee(route('admin.properties.media.destroy', [$property, $image]), false)
            ->assertSee(route('property-media.show', $image), false)
            ->assertSee(route('property-media.show', $video), false)
            ->assertSee('<img', false)
            ->assertSee('<video', false);
    }

    public function test_admin_can_add_image_and_video_and_delete_a_medium(): void
    {
        [$admin, $property] = $this->propertyFixture();

        $response = $this->actingAs($admin)->post(route('admin.properties.media.store', $property), [
            'media' => [
                UploadedFile::fake()->createWithContent('fachada.png', $this->pngContents()),
                UploadedFile::fake()->createWithContent('visita.mp4', $this->mp4Contents()),
            ],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();

        $media = $property->media()->get();
        $this->assertCount(2, $media);
        $this->assertSame(['image/png', 'video/mp4'], $media->pluck('mime_type')->all());

        $image = $media->first();
        $this->get(route('property-media.show', $image))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        $video = $media->last();
        $this->withHeader('Range', 'bytes=0-7')
            ->get(route('property-media.show', $video))
            ->assertStatus(206)
            ->assertHeader('content-range', 'bytes 0-7/'.strlen($this->mp4Contents()));

        $this->actingAs($admin)
            ->delete(route('admin.properties.media.destroy', [$property, $image]))
            ->assertRedirect();

        $this->assertModelMissing($image);
        $this->assertModelExists($video);
    }

    public function test_property_media_routes_reject_unsupported_files_and_cross_property_deletion(): void
    {
        [$admin, $property] = $this->propertyFixture();
        [, $otherProperty] = $this->propertyFixture('outro-imovel');
        $medium = $otherProperty->media()->create([
            'mime_type' => 'image/png',
            'media_base64' => base64_encode($this->pngContents()),
            'sort_order' => 0,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.properties.media.store', $property), [
                'media' => [UploadedFile::fake()->createWithContent('arquivo.txt', 'não é mídia')],
            ])
            ->assertSessionHasErrors('media.0');

        $this->actingAs($admin)
            ->delete(route('admin.properties.media.destroy', [$property, $medium]))
            ->assertNotFound();

        $this->assertModelExists($medium);
    }

    /** @return array{User, Property} */
    private function propertyFixture(string $slug = 'sala-comercial-centro'): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $group = PropertyGroup::create([
            'name' => 'Grupo Centro',
            'responsible_name' => 'Responsável',
            'phone' => '81999990000',
            'pix_key' => 'pix-grupo-centro-'.$slug,
        ]);
        $contract = Contract::create([
            'title' => 'Contrato comercial '.$slug,
            'content' => 'Conteúdo do contrato.',
            'active' => true,
        ]);
        $property = Property::create([
            'group_id' => $group->id,
            'contract_id' => $contract->id,
            'title' => 'Sala Comercial Centro',
            'slug' => $slug,
            'description' => 'Sala ampla para atendimento.',
            'type' => 'commercial',
            'usable_area' => 85.75,
            'bedrooms' => 0,
            'bathrooms' => 2,
            'parking_spaces' => 3,
            'street' => 'Avenida Central',
            'number' => '500',
            'complement' => 'Sala 3',
            'neighborhood' => 'Centro',
            'city' => 'Recife',
            'state' => 'PE',
            'postal_code' => '50000-100',
            'rent_amount' => 2350.50,
            'status' => 'available',
            'has_solar_energy' => true,
        ]);

        return [$admin, $property];
    }

    private function pngContents(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }

    private function mp4Contents(): string
    {
        return hex2bin('00000018667479706d703432000000006d70343269736f6d');
    }
}
