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
                    // JOB_NAME and BUILD_NUMBER = Golbal Jenkins Variables, "." current working directory
                    sh "docker build -t ${ImageRegistry}/${JOB_NAME}:${BUILD_NUMBER} ."
                }
            }
        }

        stage("pushImage") {
            steps {
                script {
                    echo "Pushing Image to DockerHub..."
                    withCredentials([usernamePassword(credentialsId: 'docker-login', passwordVariable: 'PASS', usernameVariable: 'USER')]) {
                        //export with password standard input for security
                        // echo helps too
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

                    // Add Telegram credentials into .env before upload
                    sh """
                    echo "Updating .env with Telegram credentials..."
                    cat > ${DotEnvFile} <<EOF
					TELEGRAM_API_ID=${TELEGRAM_API_ID}
					TELEGRAM_API_HASH=${TELEGRAM_API_HASH}
					EOF
                    """

                    sshagent(['ec2']) {
                        // Upload both files to /home/ubuntu/mywebsite/
                        sh """
                        scp -o StrictHostKeyChecking=no ${DotEnvFile} ${DockerComposeFile} ubuntu@${EC2_IP}:/home/ubuntu/mywebsite/

                        ssh -o StrictHostKeyChecking=no ubuntu@${EC2_IP} "
                            cd /home/ubuntu/mywebsite &&
                            export TELEGRAM_API_ID='${TELEGRAM_API_ID}' &&
                            export TELEGRAM_API_HASH='${TELEGRAM_API_HASH}' &&
                            docker compose --env-file .env down &&
                            docker compose --env-file .env up -d --build
                        "
                        """
                    }

                    Cleanup of local .env file from Jenkins
                    sh "rm -f ${DotEnvFile}"
                }
            }
        }
    }
}
