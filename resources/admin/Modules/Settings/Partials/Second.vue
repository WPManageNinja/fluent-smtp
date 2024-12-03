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
    import smtp2go from './Providers/Smtp2Go';
    import AmazonSes from './Providers/AmazonSes';
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
            smtp2go,
            smtp
        },
        data() {
            return {
                // ...
            };
        }
    };
</script>
