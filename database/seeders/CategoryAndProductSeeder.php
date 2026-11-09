<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CategoryAndProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Categories
        $piping = Category::firstOrCreate(
            ['slug' => 'piping-and-tubing'],
            ['name' => 'Piping & Tubing', 'default_uom' => 'Meters', 'description' => 'Copper, PVC, and drainage pipe lines']
        );
        $copperPipes = Category::firstOrCreate(
            ['slug' => 'copper-pipes-and-tubes'],
            ['name' => 'Copper Pipes & Tubes', 'parent_id' => $piping->id, 'default_uom' => 'Meters', 'description' => 'Deoxidized high phosphor seamless copper tubes']
        );
        $pvcPipes = Category::firstOrCreate(
            ['slug' => 'pvc-drain-pipes'],
            ['name' => 'PVC Drain Pipes', 'parent_id' => $piping->id, 'default_uom' => 'Meters', 'description' => 'Rigid PVC pipes for condensate drain']
        );

        $refrigerants = Category::firstOrCreate(
            ['slug' => 'refrigerants-and-gases'],
            ['name' => 'Refrigerants & Gases', 'default_uom' => 'Kg', 'description' => 'Fluorinated gases and charging cylinders']
        );
        $hfcGases = Category::firstOrCreate(
            ['slug' => 'hfc-refrigerants'],
            ['name' => 'HFC Refrigerants', 'parent_id' => $refrigerants->id, 'default_uom' => 'Kg', 'description' => 'R-410A, R-32, R-134a cylinders']
        );

        $insulation = Category::firstOrCreate(
            ['slug' => 'thermal-insulation'],
            ['name' => 'Thermal Insulation', 'default_uom' => 'Meters', 'description' => 'Elastomeric and nitrile foam thermal insulation']
        );
        $nitrileTubing = Category::firstOrCreate(
            ['slug' => 'nitrile-rubber-tubing'],
            ['name' => 'Nitrile Rubber Tubing', 'parent_id' => $insulation->id, 'default_uom' => 'Meters', 'description' => 'Closed-cell flexible elastomeric foam tubes']
        );
        $glassWool = Category::firstOrCreate(
            ['slug' => 'glass-wool-rolls'],
            ['name' => 'Glass Wool Rolls', 'parent_id' => $insulation->id, 'default_uom' => 'Meters', 'description' => 'Thermal and acoustic duct insulation rolls']
        );

        $electrical = Category::firstOrCreate(
            ['slug' => 'electrical-and-control'],
            ['name' => 'Electrical & Control', 'default_uom' => 'Meters', 'description' => 'Cables, contactors, isolators and remote controls']
        );
        $cables = Category::firstOrCreate(
            ['slug' => 'communication-cables'],
            ['name' => 'Communication Cables', 'parent_id' => $electrical->id, 'default_uom' => 'Meters', 'description' => 'Shielded 2-core and 4-core bus lines']
        );
        $breakers = Category::firstOrCreate(
            ['slug' => 'mcb-and-breakers'],
            ['name' => 'MCB & Breakers', 'parent_id' => $electrical->id, 'default_uom' => 'Pieces', 'description' => 'Miniature circuit breakers and isolators']
        );

        $fittings = Category::firstOrCreate(
            ['slug' => 'ductwork-and-fittings'],
            ['name' => 'Ductwork & Fittings', 'default_uom' => 'Pieces', 'description' => 'GI sheets, diffusers, grilles, dampers']
        );
        $diffusers = Category::firstOrCreate(
            ['slug' => 'diffusers-and-grilles'],
            ['name' => 'Diffusers & Grilles', 'parent_id' => $fittings->id, 'default_uom' => 'Pieces', 'description' => 'Ceiling swirl and 4-way supply diffusers']
        );

        // 2. Products with Variants
        $p1 = Product::updateOrCreate(
            ['name' => 'VRF Grade Seamless Copper Pipe'],
            [
                'category_id' => $piping->id,
                'sub_category_id' => $copperPipes->id,
                'brand' => 'Mandev / Indigo',
                'model_no' => 'ASTM-B280',
                'base_uom' => 'Meters',
                'package_uom' => 'Coils',
                'package_ratio' => 15.00,
                'has_variants' => true,
                'description' => 'High pressure seamless deoxidized copper tubing for VRF / VRV refrigerants',
            ]
        );
        $p1->variants()->delete();
        $p1->variants()->createMany([
            ['size_label' => '1/4" (6.35 mm)', 'sku' => 'CP-014', 'stock' => 45.00, 'min_threshold' => 5.00, 'unit_cost' => 780.00, 'is_active' => true],
            ['size_label' => '3/8" (9.52 mm)', 'sku' => 'CP-038', 'stock' => 32.50, 'min_threshold' => 5.00, 'unit_cost' => 1050.00, 'is_active' => true],
            ['size_label' => '1/2" (12.7 mm)', 'sku' => 'CP-012', 'stock' => 18.00, 'min_threshold' => 5.00, 'unit_cost' => 1440.00, 'is_active' => true],
            ['size_label' => '5/8" (15.88 mm)', 'sku' => 'CP-058', 'stock' => 4.00, 'min_threshold' => 5.00, 'unit_cost' => 1950.00, 'is_active' => true],
            ['size_label' => '3/4" (19.05 mm)', 'sku' => 'CP-034', 'stock' => 0.00, 'min_threshold' => 5.00, 'unit_cost' => 2400.00, 'is_active' => true],
        ]);

        $p2 = Product::updateOrCreate(
            ['name' => 'Refrigerant Gas R-410A'],
            [
                'category_id' => $refrigerants->id,
                'sub_category_id' => $hfcGases->id,
                'brand' => 'Chemours',
                'model_no' => 'Freon 410A',
                'base_uom' => 'Kg',
                'package_uom' => 'Cylinders',
                'package_ratio' => 11.30,
                'has_variants' => true,
                'description' => 'Eco-friendly high-performance refrigerant gas for Inverter VRF and split ACs',
            ]
        );
        $p2->variants()->delete();
        $p2->variants()->createMany([
            ['size_label' => '11.3 kg Cylinder', 'sku' => 'GAS-410A-11', 'stock' => 18.50, 'min_threshold' => 5.00, 'unit_cost' => 6200.00, 'is_active' => true],
            ['size_label' => '45.0 kg Heavy Tank', 'sku' => 'GAS-410A-45', 'stock' => 45.00, 'min_threshold' => 5.00, 'unit_cost' => 23500.00, 'is_active' => true],
        ]);

        $p3 = Product::updateOrCreate(
            ['name' => 'Refrigerant Gas R-32'],
            [
                'category_id' => $refrigerants->id,
                'sub_category_id' => $hfcGases->id,
                'brand' => 'Daikin',
                'model_no' => 'R32-ECO',
                'base_uom' => 'Kg',
                'package_uom' => 'Cylinders',
                'package_ratio' => 9.00,
                'has_variants' => true,
                'description' => 'Low GWP modern refrigerant for next-generation residential and light commercial ACs',
            ]
        );
        $p3->variants()->delete();
        $p3->variants()->createMany([
            ['size_label' => '9.0 kg Cylinder', 'sku' => 'GAS-032-09', 'stock' => 3.50, 'min_threshold' => 5.00, 'unit_cost' => 5800.00, 'is_active' => true],
        ]);

        $p4 = Product::updateOrCreate(
            ['name' => 'Class O Nitrile Rubber Insulation Tube'],
            [
                'category_id' => $insulation->id,
                'sub_category_id' => $nitrileTubing->id,
                'brand' => 'Armacell / Armaflex',
                'model_no' => 'Class O Black',
                'base_uom' => 'Meters',
                'package_uom' => 'Boxes',
                'package_ratio' => 20.00,
                'has_variants' => true,
                'description' => 'Fire-resistant closed-cell elastomeric pipe insulation for copper refrigerant lines',
            ]
        );
        $p4->variants()->delete();
        $p4->variants()->createMany([
            ['size_label' => '1/4" ID x 9mm Wall', 'sku' => 'INS-014-9', 'stock' => 60.00, 'min_threshold' => 10.00, 'unit_cost' => 85.00, 'is_active' => true],
            ['size_label' => '3/8" ID x 9mm Wall', 'sku' => 'INS-038-9', 'stock' => 42.00, 'min_threshold' => 10.00, 'unit_cost' => 110.00, 'is_active' => true],
            ['size_label' => '1/2" ID x 9mm Wall', 'sku' => 'INS-012-9', 'stock' => 2.00, 'min_threshold' => 10.00, 'unit_cost' => 135.00, 'is_active' => true],
            ['size_label' => '5/8" ID x 9mm Wall', 'sku' => 'INS-058-9', 'stock' => 25.00, 'min_threshold' => 10.00, 'unit_cost' => 165.00, 'is_active' => true],
        ]);

        $p5 = Product::updateOrCreate(
            ['name' => '4-Core Shielded Communication Cable'],
            [
                'category_id' => $electrical->id,
                'sub_category_id' => $cables->id,
                'brand' => 'Polycab',
                'model_no' => 'BMS-SH-4C',
                'base_uom' => 'Meters',
                'package_uom' => 'Drum',
                'package_ratio' => 100.00,
                'has_variants' => true,
                'description' => 'Tinned copper screened communication cable for indoor-to-outdoor VRF controller link',
            ]
        );
        $p5->variants()->delete();
        $p5->variants()->createMany([
            ['size_label' => '0.75 sq mm', 'sku' => 'CBL-4C-075', 'stock' => 250.00, 'min_threshold' => 20.00, 'unit_cost' => 48.00, 'is_active' => true],
            ['size_label' => '1.5 sq mm', 'sku' => 'CBL-4C-150', 'stock' => 0.00, 'min_threshold' => 20.00, 'unit_cost' => 78.00, 'is_active' => true],
        ]);

        $p6 = Product::updateOrCreate(
            ['name' => 'uPVC Condensate Drain Pipe (10 Bar)'],
            [
                'category_id' => $piping->id,
                'sub_category_id' => $pvcPipes->id,
                'brand' => 'Astral Pipes',
                'model_no' => 'DrainPro 10B',
                'base_uom' => 'Meters',
                'package_uom' => 'Bundles',
                'package_ratio' => 12.00,
                'has_variants' => true,
                'description' => 'Heavy duty rigid PVC pipes with socket ends for air conditioning condensation drainage',
            ]
        );
        $p6->variants()->delete();
        $p6->variants()->createMany([
            ['size_label' => '25 mm (1")', 'sku' => 'PVC-025', 'stock' => 72.00, 'min_threshold' => 15.00, 'unit_cost' => 65.00, 'is_active' => true],
            ['size_label' => '32 mm (1-1/4")', 'sku' => 'PVC-032', 'stock' => 36.00, 'min_threshold' => 15.00, 'unit_cost' => 88.00, 'is_active' => true],
        ]);

        $p7 = Product::updateOrCreate(
            ['name' => 'Aluminum 4-Way Ceiling Diffuser'],
            [
                'category_id' => $fittings->id,
                'sub_category_id' => $diffusers->id,
                'brand' => 'Airflow Systems',
                'model_no' => 'SCD-4W',
                'base_uom' => 'Pieces',
                'package_uom' => 'Cartons',
                'package_ratio' => 5.00,
                'has_variants' => true,
                'description' => 'Extruded aluminum powder coated 4-way square air supply diffuser with damper',
            ]
        );
        $p7->variants()->delete();
        $p7->variants()->createMany([
            ['size_label' => '300 x 300 mm', 'sku' => 'DIF-300', 'stock' => 14.00, 'min_threshold' => 4.00, 'unit_cost' => 1250.00, 'is_active' => true],
            ['size_label' => '450 x 450 mm', 'sku' => 'DIF-450', 'stock' => 8.00, 'min_threshold' => 4.00, 'unit_cost' => 1850.00, 'is_active' => true],
            ['size_label' => '600 x 600 mm', 'sku' => 'DIF-600', 'stock' => 3.00, 'min_threshold' => 4.00, 'unit_cost' => 2400.00, 'is_active' => true],
        ]);

        $p8 = Product::updateOrCreate(
            ['name' => 'Three Phase Motor Starter & Contactor'],
            [
                'category_id' => $electrical->id,
                'sub_category_id' => $breakers->id,
                'brand' => 'Schneider Electric',
                'model_no' => 'TeSys D',
                'base_uom' => 'Pieces',
                'package_uom' => 'Boxes',
                'package_ratio' => 1.00,
                'has_variants' => true,
                'description' => 'Magnetic motor starter contactor with thermal overload protection for HVAC chillers',
            ]
        );
        $p8->variants()->delete();
        $p8->variants()->createMany([
            ['size_label' => '18 Amp 3-Pole', 'sku' => 'MC-18A', 'stock' => 12.00, 'min_threshold' => 2.00, 'unit_cost' => 3200.00, 'is_active' => true],
            ['size_label' => '32 Amp 3-Pole', 'sku' => 'MC-32A', 'stock' => 5.00, 'min_threshold' => 2.00, 'unit_cost' => 4500.00, 'is_active' => true],
        ]);

        $p9 = Product::updateOrCreate(
            ['name' => 'Pre-insulated Duct Aluminum Foil Tape'],
            [
                'category_id' => $insulation->id,
                'sub_category_id' => $glassWool->id,
                'brand' => '3M',
                'model_no' => 'FoilWeld 30',
                'base_uom' => 'Rolls',
                'package_uom' => 'Boxes',
                'package_ratio' => 24.00,
                'has_variants' => true,
                'description' => 'Reinforced aluminum foil vapor barrier tape for HVAC duct thermal joint sealing',
            ]
        );
        $p9->variants()->delete();
        $p9->variants()->createMany([
            ['size_label' => '50 mm x 50 Meters', 'sku' => 'TAPE-50M', 'stock' => 48.00, 'min_threshold' => 10.00, 'unit_cost' => 420.00, 'is_active' => true],
            ['size_label' => '75 mm x 50 Meters', 'sku' => 'TAPE-75M', 'stock' => 15.00, 'min_threshold' => 10.00, 'unit_cost' => 590.00, 'is_active' => true],
        ]);

        $p10 = Product::updateOrCreate(
            ['name' => 'Y-Branch Copper Refnet Joint'],
            [
                'category_id' => $piping->id,
                'sub_category_id' => $copperPipes->id,
                'brand' => 'Daikin / Mandev',
                'model_no' => 'REF-Y-SET',
                'base_uom' => 'Sets',
                'package_uom' => 'Boxes',
                'package_ratio' => 1.00,
                'has_variants' => true,
                'description' => 'Precision engineered brazed copper refnet branch piping kit for VRF gas & liquid distribution',
            ]
        );
        $p10->variants()->delete();
        $p10->variants()->createMany([
            ['size_label' => 'Model 22T (Up to 5 HP)', 'sku' => 'REF-22T', 'stock' => 16.00, 'min_threshold' => 3.00, 'unit_cost' => 1850.00, 'is_active' => true],
            ['size_label' => 'Model 33T (5.1 to 10 HP)', 'sku' => 'REF-33T', 'stock' => 9.00, 'min_threshold' => 3.00, 'unit_cost' => 2450.00, 'is_active' => true],
            ['size_label' => 'Model 72T (10.1 to 20 HP)', 'sku' => 'REF-72T', 'stock' => 1.00, 'min_threshold' => 3.00, 'unit_cost' => 3600.00, 'is_active' => true],
        ]);

        $p11 = Product::updateOrCreate(
            ['name' => 'Silver Brazing Alloy Rods (2% Ag)'],
            [
                'category_id' => $piping->id,
                'sub_category_id' => $copperPipes->id,
                'brand' => 'Harris',
                'model_no' => 'Stay-Silv 2',
                'base_uom' => 'Kg',
                'package_uom' => 'Tubes',
                'package_ratio' => 1.00,
                'has_variants' => true,
                'description' => 'Phos-copper brazing rods for joining copper to copper in refrigeration piping',
            ]
        );
        $p11->variants()->delete();
        $p11->variants()->createMany([
            ['size_label' => '1.6 mm x 500 mm', 'sku' => 'BRZ-1.6', 'stock' => 12.00, 'min_threshold' => 5.00, 'unit_cost' => 2800.00, 'is_active' => true],
            ['size_label' => '2.0 mm x 500 mm', 'sku' => 'BRZ-2.0', 'stock' => 4.50, 'min_threshold' => 5.00, 'unit_cost' => 3100.00, 'is_active' => true],
        ]);

        $p12 = Product::updateOrCreate(
            ['name' => 'Digital Inverter AC Thermostat'],
            [
                'category_id' => $electrical->id,
                'sub_category_id' => $breakers->id,
                'brand' => 'Honeywell',
                'model_no' => 'Halo T6861',
                'base_uom' => 'Pieces',
                'package_uom' => 'Boxes',
                'package_ratio' => 1.00,
                'has_variants' => true,
                'description' => 'Large screen blue backlight fan coil unit digital room thermostat with Modbus',
            ]
        );
        $p12->variants()->delete();
        $p12->variants()->createMany([
            ['size_label' => 'Horizontal Wall Mount', 'sku' => 'THERM-H', 'stock' => 8.00, 'min_threshold' => 2.00, 'unit_cost' => 3400.00, 'is_active' => true],
            ['size_label' => 'Vertical Wall Mount', 'sku' => 'THERM-V', 'stock' => 0.00, 'min_threshold' => 2.00, 'unit_cost' => 3400.00, 'is_active' => true],
        ]);
    }
}
