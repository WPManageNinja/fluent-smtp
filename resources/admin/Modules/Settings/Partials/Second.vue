<template>
    <div>
        <component :is="schema.provider_name" :schema="schema" />
    </div>
</template>

<script>
    import DefaultMail from './Providers/DefaultMail';
    import mailgun from './Providers/MailGun';
    import pepipost from './Providers/PepiPost';
    import sendgrid from './Providers/SendGrid';
    import sendinblue from './Providers/SendInBlue';
    import AmazonSes from './Providers/AmazonSes';
    import Sweego from './Providers/Sweego';
    import smtp from './Providers/Smtp';

    export default {
        name: 'Second',
        props: ['schema'],
        components: {
            default: DefaultMail,
            ses: AmazonSes,
            mailgun,
            pepipost,
            sendgrid,
            sendinblue,
            smtp
        },
        data() {
            return {
                // ...
            };
        }
    };
</script>
