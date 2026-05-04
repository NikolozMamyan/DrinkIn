<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom', 'attr' => ['class' => 'form-input']])
            ->add('slug', TextType::class, ['label' => 'Slug', 'required' => false, 'attr' => ['class' => 'form-input']])
            ->add('icon', TextType::class, ['label' => 'Icone Font Awesome', 'attr' => ['class' => 'form-input']])
            ->add('submit', SubmitType::class, ['label' => 'Enregistrer la categorie']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}
