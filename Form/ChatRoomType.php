<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\ChatBundle\Form;

use Claroline\ChatBundle\Entity\ChatRoom;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChatRoomType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $typesList = array(
            ChatRoom::TEXT => 'text_only',
            ChatRoom::AUDIO => 'audio_only',
            ChatRoom::VIDEO => 'audio_video'
        );

        $builder->add(
            'name',
            'text',
            array(
                'constraints' => new NotBlank(),
                'label' => 'name',
                'translation_domain' => 'platform'
            )
        );
        $builder->add(
            'roomType',
            'choice',
            array(
                'label' => 'type',
                'choices' => $typesList
            )
        );
    }

    public function getName()
    {
        return 'chat_room_form';
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array('translation_domain' => 'chat'));
    }
}
