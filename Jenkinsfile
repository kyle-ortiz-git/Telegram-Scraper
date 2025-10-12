pipeline {
    agent any

    environment {
        ImageRegistry = 'k2ortiz'
        EC2_IP = '52.4.172.57'
        DockerComposeFile = 'docker-compose.yml'
        DotEnvFile = '.env'

        // Secure Telegram API credentials
        TELEGRAM_API_ID   = credentials('TELEGRAM_API_ID')
        TELEGRAM_API_HASH = credentials('TELEGRAM_API_HASH')
    }

    stages {

        stage("buildImage") {
            steps {
                script {
                    echo "Building Docker Image..."
                    sh "docker build -t ${ImageRegistry}/${JOB_NAME}:${BUILD_NUMBER} ."
                }
            }
        }

        stage("pushImage") {
            steps {
                script {
                    echo "Pushing Image to DockerHub..."
                    withCredentials([usernamePassword(credentialsId: 'docker-login', passwordVariable: 'PASS', usernameVariable: 'USER')]) {
                        sh "echo $PASS | docker login -u $USER --password-stdin"
                        sh "docker push ${ImageRegistry}/${JOB_NAME}:${BUILD_NUMBER}"
                    }
                }
            }
        }

        stage("deployCompose") {
            steps {
                script {
                    echo "Deploying with Docker Compose..."

                    //Add Telegram credentials into .env before upload
                    sh """
                    echo "Updating .env with Telegram credentials..."
                    cat > ${DotEnvFile} <<EOF
TELEGRAM_API_ID=${TELEGRAM_API_ID}
TELEGRAM_API_HASH=${TELEGRAM_API_HASH}
EOF
                    """

                    sshagent(['ec2']) {
                        // Upload files once to reduce redundant SCP commands
                        sh """
                        scp -o StrictHostKeyChecking=no ${DotEnvFile} ${DockerComposeFile} ubuntu@${EC2_IP}:/home/ubuntu

                        # Inject Telegram API credentials into the remote environment
                        ssh -o StrictHostKeyChecking=no ubuntu@${EC2_IP} "
                            export TELEGRAM_API_ID='${TELEGRAM_API_ID}' &&
                            export TELEGRAM_API_HASH='${TELEGRAM_API_HASH}' &&
                            docker compose -f /home/ubuntu/${DockerComposeFile} --env-file /home/ubuntu/${DotEnvFile} down &&
                            docker compose -f /home/ubuntu/${DockerComposeFile} --env-file /home/ubuntu/${DotEnvFile} up -d
                        "
                        """
                    }
                }
            }
        }
    }
}