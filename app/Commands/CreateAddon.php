<?php

namespace App\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class CreateAddon extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create-addon {--dev} {slug?} {type?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new addon';


    protected string $slug;
    protected string $vendor;
    protected string $packageName;
    protected string $classBaseName;
    protected string $label;
    protected string $namespace;
    protected string $moduleType;
    protected string $destinationPath;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dev = $this->option('dev');
        if ($dev) {
            $this->vendor = 'patrimeo';
            $this->slug = $this->argument('slug') ? $this->argument('slug') : $this->ask('Enter the slug of the addon');
            $this->packageName = $this->vendor . '/addon-' . $this->slug;
            $this->namespace = 'Patrimeo\\\\Addon' . ucfirst(Str::camel($this->slug));
            $this->label = Str::headline($this->slug);
            $this->destinationPath = '../patrimeo-addons/addon-' . $this->slug;
            $this->classBaseName = ucfirst(Str::camel($this->slug));
            $this->moduleType = $this->argument('type') ? $this->argument('type') : $this->choice('Type of the addon', ['asset_balance', 'asset_transactions', 'valuation']);
            $this->renderTemplate('common/.env');
        } else {
            $this->info('To create an addon, you need to provide the following information: ');
            $this->info('- slug');
            $this->info('- vendor');
            $this->info('For instance, if you enter "my-addon" and "vendor", your package name will be "vendor/patrimeo-addon-my-addon');

            $this->slug = $this->ask('Enter the slug of the addon');
            $this->vendor = $this->ask('Enter the vendor of the addon');

            $this->label = Str::headline($this->slug);

            $this->packageName = $this->vendor . '/patrimeo-addon-' . $this->slug;
            $this->namespace = ucfirst($this->vendor) . '\\' . ucfirst(Str::camel($this->slug));
            $this->classBaseName = ucfirst(Str::camel($this->slug));

            $this->namespace = $this->ask('Namespace of the addon', $this->namespace);

            $this->moduleType = $this->choice('Type of the addon', ['asset_balance', 'asset_transactions', 'valuation']);

            $this->destinationPath = $this->ask('Destination path of the addon', './patrimeo-addon-' . $this->slug);
        }


        $this->renderTemplate('common/composer.json.twig');
        $this->renderTemplate('common/README.md.twig');
        $this->renderTemplate('common/LICENSE.md');
        $this->renderTemplate('common/.release-it.json');

        if ($this->moduleType === 'asset_balance') {
            $this->renderTemplate('asset_balance/src/ModuleService.php.twig');
            $this->renderTemplate('asset_balance/src/ModuleServiceProvider.php.twig');
            $this->renderTemplate('asset_balance/src/ModuleDescriptor.php.twig');
        }


        $this->info('Addon created successfully');
        $this->info("Don't forget to update the README.md file and the composer.json description");
    }

    /**
     * Render a Twig template file or copy a regular file to the destination path.
     *
     * @param string $templatePath Path to the template relative to templates folder (e.g., 'common/README.md.twig' or 'common/LICENSE.md')
     * @return void
     */
    protected function renderTemplate(string $templatePath): void
    {
        $templatesBasePath = __DIR__ . '/../../templates';
        $sourceFile = $templatesBasePath . '/' . $templatePath;

        // Compute destination path
        // Remove the first segment (common, asset_balance, etc.)
        $pathParts = explode('/', $templatePath);
        array_shift($pathParts);
        $relativePath = implode('/', $pathParts);

        // Replace "Module" in filename with classBaseName if present
        if (str_contains($relativePath, 'Module')) {
            $relativePath = str_replace('Module', $this->classBaseName, $relativePath);
        }

        // Check if file is a .twig template
        $isTwig = str_ends_with($templatePath, '.twig');

        if ($isTwig) {
            // Render with Twig
            $loader = new FilesystemLoader($templatesBasePath);
            $twig = new Environment($loader);

            // Pass all properties as variables
            $variables = [
                'slug' => $this->slug,
                'vendor' => $this->vendor,
                'packageName' => $this->packageName,
                'namespace' => $this->namespace,
                'moduleType' => $this->moduleType,
                'destinationPath' => $this->destinationPath,
                'classBaseName' => $this->classBaseName,
                'label' => $this->label,
            ];

            // Render the template
            $rendered = $twig->render($templatePath, $variables);

            // Remove .twig extension from destination
            $relativePath = preg_replace('/\.twig$/', '', $relativePath);
            $content = $rendered;
        } else {
            // Copy file as-is
            $content = file_get_contents($sourceFile);
        }

        // Build full destination path
        $destinationFile = rtrim($this->destinationPath, '/') . '/' . $relativePath;

        // Create directory if it doesn't exist
        $destinationDir = dirname($destinationFile);
        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        // Write the file
        file_put_contents($destinationFile, $content);
    }
}
