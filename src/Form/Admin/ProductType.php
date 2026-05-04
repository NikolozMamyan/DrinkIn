<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Category;
use App\Entity\Product;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Product $product */
        $product = $options['data'];
        $profile = $product->getTastingProfile();

        $builder
            ->add('name', TextType::class, ['label' => 'Nom', 'attr' => ['class' => 'form-input']])
            ->add('slug', TextType::class, ['label' => 'Slug', 'required' => false, 'attr' => ['class' => 'form-input']])
            ->add('subtitle', TextType::class, ['label' => 'Sous-titre', 'attr' => ['class' => 'form-input']])
            ->add('typeLabel', TextType::class, ['label' => 'Type', 'attr' => ['class' => 'form-input']])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'label' => 'Categorie',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('region', TextType::class, ['label' => 'Region', 'required' => false, 'attr' => ['class' => 'form-input']])
            ->add('country', TextType::class, ['label' => 'Pays', 'required' => false, 'attr' => ['class' => 'form-input']])
            ->add('vintage', IntegerType::class, ['label' => 'Millesime', 'required' => false, 'attr' => ['class' => 'form-input']])
            ->add('volumeCl', IntegerType::class, ['label' => 'Volume (cl)', 'attr' => ['class' => 'form-input']])
            ->add('alcoholVolume', NumberType::class, ['label' => 'Alcool (%)', 'scale' => 1, 'attr' => ['class' => 'form-input']])
            ->add('description', TextareaType::class, ['label' => 'Description', 'attr' => ['class' => 'form-input']])
            ->add('priceEuros', NumberType::class, [
                'label' => 'Prix (EUR)',
                'mapped' => false,
                'scale' => 2,
                'data' => $product->getPriceCents() > 0 ? $product->getPriceCents() / 100 : null,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('comparePriceEuros', NumberType::class, [
                'label' => 'Prix compare (EUR)',
                'mapped' => false,
                'required' => false,
                'scale' => 2,
                'data' => null !== $product->getCompareAtPriceCents() ? $product->getCompareAtPriceCents() / 100 : null,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('ratingAverage', NumberType::class, ['label' => 'Note moyenne', 'scale' => 1, 'attr' => ['class' => 'form-input']])
            ->add('ratingCount', IntegerType::class, ['label' => 'Nombre d avis', 'attr' => ['class' => 'form-input']])
            ->add('featured', CheckboxType::class, ['label' => 'Mettre en vedette', 'required' => false])
            ->add('badgeLabel', TextType::class, ['label' => 'Badge', 'required' => false, 'attr' => ['class' => 'form-input']])
            ->add('icon', TextType::class, ['label' => 'Icone Font Awesome', 'attr' => ['class' => 'form-input']])
            ->add('fruit', IntegerType::class, [
                'label' => 'Profil Fruite',
                'mapped' => false,
                'data' => (int) ($profile['Fruite'] ?? 0),
                'attr' => ['class' => 'form-input'],
            ])
            ->add('wood', IntegerType::class, [
                'label' => 'Profil Boise',
                'mapped' => false,
                'data' => (int) ($profile['Boise'] ?? 0),
                'attr' => ['class' => 'form-input'],
            ])
            ->add('peat', IntegerType::class, [
                'label' => 'Profil Tourbe',
                'mapped' => false,
                'data' => (int) ($profile['Tourbe'] ?? 0),
                'attr' => ['class' => 'form-input'],
            ])
            ->add('submit', SubmitType::class, ['label' => 'Enregistrer le produit']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
