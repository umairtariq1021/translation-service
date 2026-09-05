<?php

namespace App\Console\Commands;

use App\Models\Locale;
use App\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateTranslations extends Command
{
    protected $signature = 'translations:generate
                            {count=100002 : Number of translation rows to generate}';

    protected $description = 'Generate realistic translation data';

    /**
     * Rows per insert. Must be divisible by 3 (en/fr/sv).
     * SQLite often allows only 999 bound parameters, so keep sqlite chunks small.
     */
    private const CHUNK_SIZE = 999;

    private const SQLITE_CHUNK_SIZE = 99;

    private const LOCALES = [
        'en',
        'fr',
        'sv',
    ];

    private const TAGS = [
        'mobile',
        'desktop',
        'web',
    ];

    public function handle(): int
    {
        $count = (int) $this->argument('count');

        if ($count < 3) {
            $this->error('Count must be at least 3.');

            return self::FAILURE;
        }

        if ($count % 3 !== 0) {
            $this->error(
                'Count must be divisible by 3 so every translation key '
                . 'can have en, fr and sv translations.'
            );

            return self::FAILURE;
        }

        $locales = Locale::query()
            ->whereIn('code', self::LOCALES)
            ->pluck('id', 'code');

        $tags = Tag::query()
            ->whereIn('name', self::TAGS)
            ->pluck('id', 'name');

        if ($locales->count() !== count(self::LOCALES)) {
            $this->error(
                'Required locales (en, fr, sv) were not found.'
            );

            return self::FAILURE;
        }

        if ($tags->count() !== count(self::TAGS)) {
            $this->error(
                'Required tags (mobile, desktop, web) were not found.'
            );

            return self::FAILURE;
        }

        $translationKeys = intdiv($count, 3);

        $this->info(
            "Generating {$count} translation rows "
            . "({$translationKeys} keys × 3 locales)..."
        );

        $templates = $this->translationTemplates();
        $now = now();
        $chunkSize = $this->chunkSize();

        $generated = 0;

        while ($generated < $count) {
            $currentChunkSize = min(
                $chunkSize,
                $count - $generated
            );

            /*
             * Always generate complete groups of 3:
             *
             * key.1 → en
             * key.1 → fr
             * key.1 → sv
             *
             * key.2 → en
             * key.2 → fr
             * key.2 → sv
             */
            $keysInChunk = intdiv($currentChunkSize, 3);

            $translations = [];

            for ($keyIndex = 1; $keyIndex <= $keysInChunk; $keyIndex++) {
                $keyNumber = intdiv($generated, 3) + $keyIndex;

                $template = $templates[
                    ($keyNumber - 1) % count($templates)
                ];

                $translationKey = $template['key'] . '.' . $keyNumber;

                foreach (self::LOCALES as $localeCode) {
                    $translations[] = [
                        'locale_id' => $locales[$localeCode],
                        'translation_key' => $translationKey,
                        'content' => $this->translateContent(
                            $template['content'],
                            $localeCode
                        ),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($translations === []) {
                break;
            }

            DB::transaction(function () use (
                $translations,
                $tags
            ): void {
                /*
                 * One bulk INSERT for translations.
                 */
                DB::table('translations')->insert($translations);

                /*
                 * Retrieve the IDs for only this chunk.
                 *
                 * We use the generated translation keys rather than
                 * insertGetId(), so there is no INSERT per record.
                 */
                $keys = array_unique(
                    array_column($translations, 'translation_key')
                );

                $translationRows = DB::table('translations')
                    ->select([
                        'id',
                        'translation_key',
                    ])
                    ->whereIn('translation_key', $keys)
                    ->get();

                $tagRows = [];

                foreach ($translationRows as $translation) {
                    /*
                     * Deterministic tag assignment.
                     *
                     * Different records get different tag combinations
                     * without calling random() for every record.
                     */
                    $number = (int) substr(
                        strrchr($translation->translation_key, '.'),
                        1
                    );

                    $tagNames = match ($number % 4) {
                        0 => ['web'],
                        1 => ['mobile'],
                        2 => ['desktop'],
                        default => ['web', 'mobile'],
                    };

                    foreach ($tagNames as $tagName) {
                        $tagRows[] = [
                            'translation_id' => $translation->id,
                            'tag_id' => $tags[$tagName],
                        ];
                    }
                }

                /*
                 * One bulk INSERT for the pivot table.
                 */
                if ($tagRows !== []) {
                    DB::table('tag_translation')->insert($tagRows);
                }
            });

            $generated += count($translations);

            $this->output->write(
                "\rGenerated {$generated}/{$count}"
            );
        }

        $this->newLine();

        $this->info(
            "Successfully generated {$count} translation rows."
        );

        return self::SUCCESS;
    }

    private function chunkSize(): int
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return self::SQLITE_CHUNK_SIZE;
        }

        return self::CHUNK_SIZE;
    }

    /**
     * @return array<int, array{key: string, content: string}>
     */
    private function translationTemplates(): array
    {
        return [
            [
                'key' => 'home.title',
                'content' => 'Welcome to our application',
            ],
            [
                'key' => 'home.subtitle',
                'content' => 'Manage everything from one place',
            ],
            [
                'key' => 'auth.login',
                'content' => 'Login to your account',
            ],
            [
                'key' => 'auth.logout',
                'content' => 'Logout from your account',
            ],
            [
                'key' => 'auth.register',
                'content' => 'Create an account',
            ],
            [
                'key' => 'auth.forgot_password',
                'content' => 'Forgot your password?',
            ],
            [
                'key' => 'profile.title',
                'content' => 'Your Profile',
            ],
            [
                'key' => 'profile.update',
                'content' => 'Update your profile',
            ],
            [
                'key' => 'dashboard.title',
                'content' => 'Dashboard',
            ],
            [
                'key' => 'dashboard.welcome',
                'content' => 'Welcome back',
            ],
            [
                'key' => 'notification.success',
                'content' => 'Operation completed successfully',
            ],
            [
                'key' => 'notification.error',
                'content' => 'Something went wrong',
            ],
            [
                'key' => 'notification.saved',
                'content' => 'Changes saved successfully',
            ],
            [
                'key' => 'checkout.title',
                'content' => 'Checkout',
            ],
            [
                'key' => 'checkout.payment_success',
                'content' => 'Payment completed successfully',
            ],
            [
                'key' => 'checkout.payment_failed',
                'content' => 'Payment could not be completed',
            ],
            [
                'key' => 'product.title',
                'content' => 'Product',
            ],
            [
                'key' => 'product.description',
                'content' => 'Product description',
            ],
            [
                'key' => 'cart.empty',
                'content' => 'Your cart is empty',
            ],
            [
                'key' => 'cart.checkout',
                'content' => 'Proceed to checkout',
            ],
            [
                'key' => 'common.save',
                'content' => 'Save',
            ],
            [
                'key' => 'common.cancel',
                'content' => 'Cancel',
            ],
            [
                'key' => 'common.delete',
                'content' => 'Delete',
            ],
            [
                'key' => 'common.edit',
                'content' => 'Edit',
            ],
        ];
    }

    private function translateContent(
        string $content,
        string $locale
    ): string {
        if ($locale === 'en') {
            return $content;
        }

        $translations = [
            'fr' => [
                'Welcome to our application'
                    => 'Bienvenue dans notre application',
                'Manage everything from one place'
                    => 'Gérez tout depuis un seul endroit',
                'Login to your account'
                    => 'Connectez-vous à votre compte',
                'Logout from your account'
                    => 'Déconnectez-vous de votre compte',
                'Create an account'
                    => 'Créer un compte',
                'Forgot your password?'
                    => 'Mot de passe oublié ?',
                'Your Profile'
                    => 'Votre profil',
                'Update your profile'
                    => 'Mettre à jour votre profil',
                'Dashboard'
                    => 'Tableau de bord',
                'Welcome back'
                    => 'Bon retour',
                'Operation completed successfully'
                    => 'Opération terminée avec succès',
                'Something went wrong'
                    => 'Une erreur est survenue',
                'Changes saved successfully'
                    => 'Modifications enregistrées avec succès',
                'Checkout'
                    => 'Paiement',
                'Payment completed successfully'
                    => 'Paiement effectué avec succès',
                'Payment could not be completed'
                    => 'Le paiement n’a pas pu être effectué',
                'Product'
                    => 'Produit',
                'Product description'
                    => 'Description du produit',
                'Your cart is empty'
                    => 'Votre panier est vide',
                'Proceed to checkout'
                    => 'Passer au paiement',
                'Save'
                    => 'Enregistrer',
                'Cancel'
                    => 'Annuler',
                'Delete'
                    => 'Supprimer',
                'Edit'
                    => 'Modifier',
            ],

            'sv' => [
                'Welcome to our application'
                    => 'Välkommen till vår applikation',
                'Manage everything from one place'
                    => 'Hantera allt från en plats',
                'Login to your account'
                    => 'Logga in på ditt konto',
                'Logout from your account'
                    => 'Logga ut från ditt konto',
                'Create an account'
                    => 'Skapa ett konto',
                'Forgot your password?'
                    => 'Glömt ditt lösenord?',
                'Your Profile'
                    => 'Din profil',
                'Update your profile'
                    => 'Uppdatera din profil',
                'Dashboard'
                    => 'Instrumentpanel',
                'Welcome back'
                    => 'Välkommen tillbaka',
                'Operation completed successfully'
                    => 'Åtgärden slutfördes',
                'Something went wrong'
                    => 'Något gick fel',
                'Changes saved successfully'
                    => 'Ändringarna har sparats',
                'Checkout'
                    => 'Kassa',
                'Payment completed successfully'
                    => 'Betalningen genomfördes',
                'Payment could not be completed'
                    => 'Betalningen kunde inte genomföras',
                'Product'
                    => 'Produkt',
                'Product description'
                    => 'Produktbeskrivning',
                'Your cart is empty'
                    => 'Din varukorg är tom',
                'Proceed to checkout'
                    => 'Gå till kassan',
                'Save'
                    => 'Spara',
                'Cancel'
                    => 'Avbryt',
                'Delete'
                    => 'Ta bort',
                'Edit'
                    => 'Redigera',
            ],
        ];

        return $translations[$locale][$content] ?? $content;
    }
}